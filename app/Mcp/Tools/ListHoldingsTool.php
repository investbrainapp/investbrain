<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Resources\HoldingResource;
use App\Models\Holding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-holdings')]
#[Description('List the holdings belonging to the authenticated user.')]
#[IsReadOnly]
class ListHoldingsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'items_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Holding::myHoldings()
            ->with(['market_data', 'transactions'])
            ->paginate(
                perPage: $validated['items_per_page'] ?? 15,
                page: $validated['page'] ?? 1,
            );

        return Response::text((string) json_encode([
            'data' => HoldingResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->description('The page number to retrieve.')
                ->default(1),

            'items_per_page' => $schema->integer()
                ->description('Number of items to return per page.')
                ->default(15),
        ];
    }
}
