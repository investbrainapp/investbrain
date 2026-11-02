<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteRegistrar;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Keeps openapi.json in step with the API routes, sorts, and filters it documents.
 */
class OpenApiSpecTest extends ApiTestCase
{
    use RefreshDatabase;

    /**
     * HTTP methods that can appear as operations in an OpenAPI path item.
     */
    private const OPERATION_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function listEndpoints(): array
    {
        return [
            'portfolios' => ['/portfolio', 'api.portfolio.index'],
            'transactions' => ['/transaction', 'api.transaction.index'],
            'holdings' => ['/holding', 'api.holding.index'],
        ];
    }

    public function test_every_api_route_is_documented_and_every_documented_operation_exists(): void
    {
        $registered = collect(RouteRegistrar::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => Str::startsWith((string) $route->getName(), 'api.')
                && Str::startsWith($route->uri(), 'api/'))
            ->flatMap(function (Route $route): array {
                $methods = array_diff($route->methods(), ['HEAD']);

                // apiResource registers PATCH alongside PUT for updates; the spec documents PUT
                if (in_array('PUT', $methods, true)) {
                    $methods = array_diff($methods, ['PATCH']);
                }

                $path = $this->normalizePath(Str::after($route->uri(), 'api'));

                return array_map(fn (string $method): string => $method.' '.$path, array_values($methods));
            })
            ->unique()
            ->sort()
            ->values()
            ->all();

        $documented = collect($this->spec()['paths'])
            ->flatMap(fn (array $pathItem, string $path): array => collect($pathItem)
                ->keys()
                ->filter(fn (string $method): bool => in_array($method, self::OPERATION_METHODS, true))
                ->map(fn (string $method): string => strtoupper($method).' '.$this->normalizePath($path))
                ->all())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            $documented,
            $registered,
            'openapi.json and routes/api.php disagree. Add missing routes to the spec or remove operations that no longer exist.'
        );
    }

    #[DataProvider('listEndpoints')]
    public function test_documented_sorts_match_what_the_endpoint_allows(string $path, string $routeName): void
    {
        $message = $this->actingAs(User::factory()->create())
            ->getJson(route($routeName, ['sort' => 'not_a_documented_column']))
            ->assertUnprocessable()
            ->json('errors.sort.0');

        $this->assertEqualsCanonicalizing(
            $this->documentedSorts($path),
            $this->namesFromMessage($message, 'The sort field may only contain: '),
            "The sorts documented for GET {$path} in openapi.json don't match the endpoint's allowed sorts."
        );
    }

    #[DataProvider('listEndpoints')]
    public function test_documented_filters_match_what_the_endpoint_allows(string $path, string $routeName): void
    {
        $message = $this->actingAs(User::factory()->create())
            ->getJson(route($routeName, ['filter' => ['not_a_documented_filter' => '1']]))
            ->assertUnprocessable()
            ->json('errors.filter.0');

        $this->assertEqualsCanonicalizing(
            array_keys($this->documentedFilters($path)),
            $this->namesFromMessage($message, 'The filter field may only contain: '),
            "The filters documented for GET {$path} in openapi.json don't match the endpoint's allowed filters."
        );
    }

    #[DataProvider('listEndpoints')]
    public function test_every_documented_sort_and_filter_can_be_used(string $path, string $routeName): void
    {
        $this->actingAs(User::factory()->create());

        foreach ($this->documentedSorts($path) as $sort) {
            $response = $this->getJson(route($routeName, ['sort' => '-'.$sort]));

            $this->assertSame(200, $response->status(), "GET {$path}?sort=-{$sort} failed with status {$response->status()}.");
        }

        foreach ($this->documentedFilters($path) as $name => $schema) {
            $response = $this->getJson(route($routeName, ['filter' => [$name => $this->sampleFilterValue($schema)]]));

            $this->assertSame(200, $response->status(), "GET {$path}?filter[{$name}] failed with status {$response->status()}.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return json_decode((string) file_get_contents(base_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Replace every path parameter with a placeholder so `{id}` and `{portfolio}` compare equal.
     */
    private function normalizePath(string $path): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', $path);
    }

    /**
     * Find a query parameter of a list endpoint's GET operation, resolving component references.
     *
     * @return array<string, mixed>
     */
    private function listParameter(string $path, string $name): array
    {
        $spec = $this->spec();

        foreach ($spec['paths'][$path]['get']['parameters'] ?? [] as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $spec['components']['parameters'][Str::afterLast($parameter['$ref'], '/')];
            }

            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }

        $this->fail("GET {$path} in openapi.json has no `{$name}` parameter.");
    }

    /**
     * @return array<int, string>
     */
    private function documentedSorts(string $path): array
    {
        return collect($this->listParameter($path, 'sort')['schema']['items']['enum'])
            ->reject(fn (string $sort): bool => Str::startsWith($sort, '-'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function documentedFilters(string $path): array
    {
        return $this->listParameter($path, 'filter')['schema']['properties'];
    }

    /**
     * Turn "The sort field may only contain: a, b, c" into ['a', 'b', 'c'].
     *
     * @return array<int, string>
     */
    private function namesFromMessage(?string $message, string $prefix): array
    {
        $this->assertNotNull($message);
        $this->assertStringStartsWith($prefix, $message);

        return explode(', ', rtrim(Str::after($message, $prefix), '.'));
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function sampleFilterValue(array $schema): string
    {
        return match (true) {
            ($schema['type'] ?? null) === 'boolean' => 'true',
            ($schema['format'] ?? null) === 'uuid' => (string) Str::uuid(),
            isset($schema['enum']) => (string) $schema['enum'][0],
            default => 'AAPL',
        };
    }
}
