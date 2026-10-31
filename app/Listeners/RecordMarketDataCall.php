<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MarketDataCallCompleted;
use App\Models\MarketDataProviderFailure;
use App\Models\MarketDataProviderMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RecordMarketDataCall
{
    /**
     * Aggregate each provider call into an hourly bucket and keep the details of failures.
     */
    public function handle(MarketDataCallCompleted $event): void
    {
        try {
            $success = (int) $event->successful;
            $failure = (int) ! $event->successful;
            $fallback = (int) ($event->successful && $event->attempt > 0);
            $duration = (int) round($event->durationMs);

            $table = (new MarketDataProviderMetric)->getTable();

            MarketDataProviderMetric::query()->upsert([[
                'provider' => $event->provider,
                'method' => $event->method,
                'hour' => now()->startOfHour(),
                'success_count' => $success,
                'failure_count' => $failure,
                'fallback_count' => $fallback,
                'total_duration_ms' => $duration,
            ]], ['provider', 'method', 'hour'], [
                'success_count' => DB::raw("{$table}.success_count + {$success}"),
                'failure_count' => DB::raw("{$table}.failure_count + {$failure}"),
                'fallback_count' => DB::raw("{$table}.fallback_count + {$fallback}"),
                'total_duration_ms' => DB::raw("{$table}.total_duration_ms + {$duration}"),
            ]);

            if (! $event->successful) {
                MarketDataProviderFailure::create([
                    'provider' => $event->provider,
                    'method' => $event->method,
                    'symbol' => Str::limit((string) $event->symbol, 25, ''),
                    'error' => Str::limit((string) $event->error, 2000),
                ]);
            }
        } catch (Throwable $e) {
            // Recording metrics must never break a market data call
            report($e);
        }
    }
}
