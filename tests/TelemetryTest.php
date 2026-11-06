<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Telemetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected string $instanceIdPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instanceIdPath = sys_get_temp_dir().'/investbrain-telemetry-'.Str::random(8).'/.instance-id';

        config()->set('investbrain.self_hosted', true);
        config()->set('investbrain.version', 'v1.2.3');
        config()->set('investbrain.provider', 'yahoo,my-own-provider');
        config()->set('investbrain.telemetry', [
            'enabled' => true,
            'url' => 'https://telemetry.test/telemetry',
            'instance_id_path' => $this->instanceIdPath,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->instanceIdPath));

        parent::tearDown();
    }

    public function test_it_sends_the_version_environment_and_aggregate_counts(): void
    {
        Http::fake(['telemetry.test/*' => Http::response(['ok' => true])]);

        $user = User::factory()->create(['email' => 'private@example.com', 'name' => 'Private Person', 'options' => ['locale' => 'fr', 'display_currency' => 'EUR']]);
        $this->actingAs($user);
        $portfolio = Portfolio::factory()->create(['title' => 'Secret portfolio']);
        Transaction::factory()->buy(2)->portfolio($portfolio->id)->symbol('AAPL')->create();

        $this->artisan('telemetry:send')
            ->expectsOutputToContain('Sent anonymous usage statistics')
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'https://telemetry.test/telemetry'
                && $body['schema'] === Telemetry::SCHEMA
                && $body['event'] === 'started'
                && Str::isUuid($body['instance_id'])
                && $body['version'] === 'v1.2.3'
                && $body['environment']['database'] === 'sqlite'
                && $body['features']['market_data_providers'] === ['yahoo', 'custom']
                && $body['stats']['users'] === 1
                && $body['stats']['portfolios'] >= 1
                && $body['stats']['transactions'] === 1
                && $body['stats']['open_holdings'] === 1
                && $body['stats']['locales'] === ['fr' => 1]
                && $body['stats']['display_currencies'] === ['EUR' => 1];
        });

        Http::assertSent(function (Request $request): bool {
            $json = json_encode($request->data());

            return ! str_contains($json, 'private@example.com')
                && ! str_contains($json, 'Private Person')
                && ! str_contains($json, 'Secret portfolio')
                && ! str_contains($json, 'AAPL')
                && ! str_contains($json, 'my-own-provider');
        });
    }

    public function test_the_instance_id_is_kept_between_starts(): void
    {
        $first = Telemetry::instanceId();

        $this->assertTrue(Str::isUuid($first));
        $this->assertSame($first, Telemetry::instanceId());
        $this->assertSame($first, trim(File::get($this->instanceIdPath)));

        File::put($this->instanceIdPath, 'not-a-uuid');
        $this->assertNotSame($first, $replaced = Telemetry::instanceId());
        $this->assertTrue(Str::isUuid($replaced));
    }

    public function test_nothing_is_sent_when_turned_off(): void
    {
        Http::fake();

        config()->set('investbrain.telemetry.enabled', false);
        $this->artisan('telemetry:send')->expectsOutput('Telemetry is turned off.')->assertSuccessful();

        config()->set('investbrain.telemetry.enabled', true);
        config()->set('investbrain.self_hosted', false);
        $this->artisan('telemetry:send')->expectsOutput('Telemetry is turned off.')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_show_prints_the_payload_without_sending_it(): void
    {
        Http::fake();

        $this->artisan('telemetry:send --show')
            ->expectsOutputToContain('"version": "v1.2.3"')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_an_error_response_does_not_fail_the_command(): void
    {
        Http::fake(['telemetry.test/*' => Http::response('down', 503)]);

        $this->artisan('telemetry:send')->expectsOutputToContain('Could not send')->assertSuccessful();
    }

    public function test_an_unreachable_server_does_not_fail_the_command(): void
    {
        Http::fake(['telemetry.test/*' => fn () => throw new ConnectionException('unreachable')]);

        $this->artisan('telemetry:send')->expectsOutputToContain('Could not send')->assertSuccessful();
    }
}
