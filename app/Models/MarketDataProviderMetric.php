<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
