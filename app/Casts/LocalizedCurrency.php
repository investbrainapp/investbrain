<?php

declare(strict_types=1);

namespace App\Casts;

use App\Models\Currency;
use App\Models\MarketData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class LocalizedCurrency implements CastsAttributes
{
    /**
     * Cast the given value to user's display currency
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return Currency::toDisplayCurrency($value);
    }

    /**
     * Prepare the given value for storage in base currency
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // for models without currency as an attribute, we need to ensure market data is available.
        if (is_null($model?->currency) && is_null($model->market_data)) {

            $model->setRelation('market_data', MarketData::where('symbol', $attributes['symbol'])->first());
        }

        return Currency::toBaseCurrency($value, $model?->currency ?? $model->market_data?->currency);
    }
}
