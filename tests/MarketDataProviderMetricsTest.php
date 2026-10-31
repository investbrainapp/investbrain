<?php

declare(strict_types=1);

namespace Tests;

use App\Events\MarketDataCallCompleted;
use App\Interfaces\MarketData\FallbackInterface;
use App\Models\MarketDataProviderFailure;
use App\Models\MarketDataProviderMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketDataProviderMetricsTest extends TestCase
{
    use RefreshDatabase;

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
}
