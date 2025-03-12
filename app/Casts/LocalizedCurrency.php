<?php

declare(strict_types=1);

namespace App\Casts;

use App\Models\Currency;
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
        return (float) $value;
    }

    /**
     * Prepare the given value for storage in base currency
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // for market data and transactions the `currency` attribute is available...
        // but for dividends and other types, need to make sure `market_data` is loaded
        if (is_null($model?->currency)) {

            $model->loadMarketData();
        }

        return Currency::toBaseCurrency($value, $model?->currency ?? $model->market_data?->currency);
    }
}
