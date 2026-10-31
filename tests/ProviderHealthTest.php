<?php

declare(strict_types=1);

namespace Tests;

use App\Events\MarketDataCallCompleted;
use App\Interfaces\MarketData\FallbackInterface;
use App\Models\MarketDataProviderFailure;
use App\Models\MarketDataProviderMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

class ProviderHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-31 15:30:00');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('provider-health'))->assertRedirect(route('login'));
    }

    public function test_non_admins_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('provider-health'))->assertForbidden();
    }

    public function test_only_admins_pass_the_admin_gate(): void
    {
        $nullAdmin = User::factory()->create();
        $nullAdmin->forceFill(['admin' => null])->save();

        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('admin'));
        $this->assertFalse(Gate::forUser(User::factory()->create(['admin' => false]))->allows('admin'));
        $this->assertFalse(Gate::forUser($nullAdmin)->allows('admin'));
    }

    public function test_component_cannot_be_used_by_non_admins(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('provider-health')->assertForbidden();
    }

    public function test_admins_see_provider_health_and_recent_failures(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->metric('yahoo', '2026-10-31 14:00', successes: 18, failures: 2, duration: 2000);
        $this->travelTo('2026-10-31 14:10:00');
        MarketDataProviderFailure::create(['provider' => 'yahoo', 'method' => 'quote', 'symbol' => 'ACME', 'error' => '<script>alert("rate limited")</script>']);
        $this->travelTo('2026-10-31 15:30:00');

        $this->get(route('provider-health'))
            ->assertOk()
            ->assertSeeInOrder(['yahoo', 'quote', '20', '90.0%', '100 ms', '1 hour ago'])
            ->assertSee('ACME')
            ->assertSee('<script>alert("rate limited")</script>')
            ->assertDontSee('<script>alert("rate limited")</script>', false);
    }

    public function test_time_period_can_be_changed(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->metric('finnhub', '2026-10-28 09:00', successes: 5);

        Livewire::test('provider-health')
            ->assertSee('Nothing to show here yet')
            ->assertDontSee('finnhub')
            ->set('range', '7d')
            ->assertSee('finnhub')
            ->assertSee('Last 7 days');
    }

    public function test_unknown_time_period_falls_back_to_the_last_24_hours(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->metric('finnhub', '2026-10-28 09:00', successes: 5);

        Livewire::test('provider-health')
            ->set('range', '365d')
            ->assertOk()
            ->assertSee('Last 24 hours')
            ->assertDontSee('finnhub');
    }

    public function test_sidebar_links_to_the_page_only_for_admins(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertDontSee(route('provider-health'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSee(route('provider-health'));
    }

    public function test_provider_calls_are_aggregated_into_hourly_buckets(): void
    {
        MarketDataCallCompleted::dispatch('yahoo', 'quote', 'AAPL', 120.4, false, 0, 'Too many requests');
        MarketDataCallCompleted::dispatch('finnhub', 'quote', 'AAPL', 80.2, true, 1);
        MarketDataCallCompleted::dispatch('yahoo', 'quote', 'MSFT', 40.0, true, 0);

        $yahoo = MarketDataProviderMetric::where('provider', 'yahoo')->sole();
        $this->assertSame(1, $yahoo->success_count);
        $this->assertSame(1, $yahoo->failure_count);
        $this->assertSame(0, $yahoo->fallback_count);
        $this->assertSame(160, $yahoo->total_duration_ms);
        $this->assertTrue($yahoo->hour->equalTo(now()->startOfHour()));

        $this->assertSame(1, MarketDataProviderMetric::where('provider', 'finnhub')->sole()->fallback_count);

        $failure = MarketDataProviderFailure::sole();
        $this->assertSame(['yahoo', 'quote', 'AAPL', 'Too many requests'], [$failure->provider, $failure->method, $failure->symbol, $failure->error]);

        $this->travel(1)->hours();
        MarketDataCallCompleted::dispatch('yahoo', 'quote', 'AAPL', 10, true, 0);

        $this->assertSame(2, MarketDataProviderMetric::where('provider', 'yahoo')->count());
    }

    public function test_real_provider_calls_are_recorded(): void
    {
        config()->set('investbrain.provider', 'fake');

        app(FallbackInterface::class)->quote('AAPL');

        $this->assertSame(1, MarketDataProviderMetric::where(['provider' => 'fake', 'method' => 'quote'])->sole()->success_count);
    }

    public function test_old_failures_are_pruned(): void
    {
        MarketDataProviderFailure::create(['provider' => 'yahoo', 'method' => 'quote', 'error' => 'Old']);

        $this->travel(MarketDataProviderFailure::RETENTION_DAYS + 1)->days();
        MarketDataProviderFailure::create(['provider' => 'yahoo', 'method' => 'quote', 'error' => 'Recent']);

        $this->artisan('model:prune', ['--model' => [MarketDataProviderFailure::class]])->assertSuccessful();

        $this->assertSame(['Recent'], MarketDataProviderFailure::pluck('error')->all());
    }

    public function test_metrics_are_totalled_per_provider_and_method_within_the_period(): void
    {
        $this->metric('yahoo', '2026-10-31 14:00', successes: 8, failures: 2, duration: 1000);
        $this->metric('yahoo', '2026-10-31 12:00', successes: 10, fallbacks: 9, duration: 1000);
        $this->metric('yahoo', '2026-10-28 09:00', successes: 100);
        $this->metric('finnhub', '2026-10-31 13:00', failures: 4, duration: 400);

        $totals = MarketDataProviderMetric::since(now()->subDay())->withTotals()->orderBy('provider')->get();

        $this->assertSame(['finnhub', 'yahoo'], $totals->pluck('provider')->all());

        [$finnhub, $yahoo] = $totals->all();

        $this->assertSame([18, 2, 9, 20], [$yahoo->success_count, $yahoo->failure_count, $yahoo->fallback_count, $yahoo->calls]);
        $this->assertSame([90.0, 50.0, 100.0], [$yahoo->success_rate, $yahoo->fallback_rate, $yahoo->average_duration_ms]);

        $this->assertSame(0.0, $finnhub->success_rate);
        $this->assertNull($finnhub->fallback_rate, 'There is no fallback rate without successful calls.');
    }

    public function test_rates_are_null_without_calls(): void
    {
        $metric = new MarketDataProviderMetric(['success_count' => 0, 'failure_count' => 0, 'fallback_count' => 0, 'total_duration_ms' => 0]);

        $this->assertSame([0, null, null], [$metric->calls, $metric->success_rate, $metric->average_duration_ms]);
    }

    public function test_latest_failure_is_found_for_each_provider_and_method(): void
    {
        foreach ([
            ['yahoo', 'quote', 'Too old', '2026-10-29 10:00'],
            ['yahoo', 'quote', 'Earlier', '2026-10-31 10:00'],
            ['yahoo', 'quote', 'Latest yahoo quote', '2026-10-31 14:00'],
            ['yahoo', 'history', 'Latest yahoo history', '2026-10-31 11:00'],
            ['finnhub', 'quote', 'Latest finnhub quote', '2026-10-31 09:00'],
        ] as [$provider, $method, $error, $at]) {
            $this->travelTo($at);
            MarketDataProviderFailure::create(compact('provider', 'method', 'error'));
        }

        $this->travelTo('2026-10-31 15:30:00');

        $latest = MarketDataProviderFailure::since(now()->subDay())->latestForEachProvider()->latest()->pluck('error')->all();

        $this->assertSame(['Latest yahoo quote', 'Latest yahoo history', 'Latest finnhub quote'], $latest);
    }

    public function test_timeline_buckets_by_hour_for_short_periods_and_by_day_for_longer_ones(): void
    {
        $this->metric('yahoo', '2026-10-31 14:00', successes: 8, failures: 2);
        $this->metric('finnhub', '2026-10-31 14:00', failures: 1);
        $this->metric('yahoo', '2026-10-28 09:00', successes: 100);

        $hourly = MarketDataProviderMetric::timeline(now()->subDay());

        $this->assertCount(25, $hourly);
        $this->assertSame(['calls' => 11, 'failures' => 3], $hourly['2026-10-31 14:00']);
        $this->assertSame(['calls' => 0, 'failures' => 0], $hourly['2026-10-31 15:00']);

        $daily = MarketDataProviderMetric::timeline(now()->subDays(7));

        $this->assertSame('2026-10-24', array_key_first($daily));
        $this->assertSame(['calls' => 100, 'failures' => 0], $daily['2026-10-28']);
        $this->assertSame(['calls' => 11, 'failures' => 3], $daily['2026-10-31']);

        $this->assertSame(['calls' => 1, 'failures' => 1], MarketDataProviderMetric::timeline(now()->subDay(), 'finnhub')['2026-10-31 14:00']);
    }

    protected function metric(string $provider, string $hour, int $successes = 0, int $failures = 0, int $fallbacks = 0, int $duration = 0): MarketDataProviderMetric
    {
        return MarketDataProviderMetric::create([
            'provider' => $provider,
            'method' => 'quote',
            'hour' => $hour,
            'success_count' => $successes,
            'failure_count' => $failures,
            'fallback_count' => $fallbacks,
            'total_duration_ms' => $duration,
        ]);
    }
}
