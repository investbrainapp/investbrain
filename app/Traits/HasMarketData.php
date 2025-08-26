<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\MarketData;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasMarketData
{
    /**
     * Related market data for model
     *
     * @return void
     */
    public function market_data(): HasOne
    {
        return $this->hasOne(MarketData::class, 'symbol', 'symbol');
    }

    /**
     * Gracefully loads related market data as relationship (creates if doesn't exist)
     */
    public function loadMarketData(): void
    {
        if (is_null($this->market_data)) {

            $this->setRelation('market_data', MarketData::getMarketData($this->attributes['symbol']));
        }
    }

    public function scopeNotBaseCurrency($query): void
    {
        $query->with('market_data')
            ->whereRelation(
                'market_data',
                'currency',
                '!=',
                config('investbrain.base_currency')
            );
    }
}
