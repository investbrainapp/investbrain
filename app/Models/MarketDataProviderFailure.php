<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class MarketDataProviderFailure extends Model
{
    use MassPrunable;

    /**
     * Number of days failures are kept for.
     */
    public const RETENTION_DAYS = 30;

    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'method',
        'symbol',
        'error',
    ];

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(static::RETENTION_DAYS));
    }
}
