<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
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

    public function scopeSince(Builder $query, CarbonInterface $since): Builder
    {
        return $query->where('created_at', '>=', $since);
    }

    public function scopeForProvider(Builder $query, string $provider, ?string $method = null): Builder
    {
        return $query->where('provider', $provider)
            ->when($method, fn (Builder $query) => $query->where('method', $method));
    }

    /**
     * Only the most recent failure for each provider and method matching the query so far.
     */
    public function scopeLatestForEachProvider(Builder $query): Builder
    {
        return $query->whereIn('id', $query->clone()->reorder()->selectRaw('max(id)')->groupBy('provider', 'method'));
    }
}
