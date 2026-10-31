<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Hourly totals of calls made to each market data provider method.
 */
class MarketDataProviderMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'method',
        'hour',
        'success_count',
        'failure_count',
        'fallback_count',
        'total_duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'hour' => 'datetime',
            'success_count' => 'integer',
            'failure_count' => 'integer',
            'fallback_count' => 'integer',
            'total_duration_ms' => 'integer',
        ];
    }

    public function scopeSince(Builder $query, CarbonInterface $since): Builder
    {
        return $query->where('hour', '>=', $since);
    }

    /**
     * Sum the hourly buckets into one row per provider and method, keeping the column names.
     */
    public function scopeWithTotals(Builder $query): Builder
    {
        return $query->select('provider', 'method')
            ->selectRaw('sum(success_count) as success_count, sum(failure_count) as failure_count')
            ->selectRaw('sum(fallback_count) as fallback_count, sum(total_duration_ms) as total_duration_ms')
            ->groupBy('provider', 'method');
    }

    /**
     * Total calls, successful or not.
     */
    protected function calls(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->success_count + $this->failure_count
        );
    }

    /**
     * Percentage of calls that succeeded, or null when there were no calls.
     */
    protected function successRate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => $this->calls ? $this->success_count * 100 / $this->calls : null
        );
    }

    /**
     * Percentage of successful calls that only succeeded after an earlier provider failed.
     */
    protected function fallbackRate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => $this->success_count ? $this->fallback_count * 100 / $this->success_count : null
        );
    }

    /**
     * Average call duration in milliseconds, or null when there were no calls.
     */
    protected function averageDurationMs(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => $this->calls ? $this->total_duration_ms / $this->calls : null
        );
    }

    /**
     * Calls and failures since the given time, bucketed by hour for ranges up to 48 hours and by day otherwise.
     *
     * @return array<string, array{calls: int, failures: int}>
     */
    public static function timeline(CarbonInterface $since, ?string $provider = null): array
    {
        $hourly = $since->diffInHours(now()) <= 48;
        $format = $hourly ? 'Y-m-d H:00' : 'Y-m-d';

        $buckets = [];
        $cursor = CarbonImmutable::parse($since)->startOf($hourly ? 'hour' : 'day');

        for (; $cursor->lte(now()); $cursor = $hourly ? $cursor->addHour() : $cursor->addDay()) {
            $buckets[$cursor->format($format)] = ['calls' => 0, 'failures' => 0];
        }

        static::query()
            ->since($since)
            ->when($provider, fn (Builder $query) => $query->where('provider', $provider))
            ->get(['hour', 'success_count', 'failure_count'])
            ->each(function (self $metric) use (&$buckets, $format): void {
                $key = $metric->hour->format($format);

                if (! Arr::exists($buckets, $key)) {
                    return;
                }

                $buckets[$key]['calls'] += $metric->calls;
                $buckets[$key]['failures'] += $metric->failure_count;
            });

        return $buckets;
    }
}
