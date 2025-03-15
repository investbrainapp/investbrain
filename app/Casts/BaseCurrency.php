<?php

declare(strict_types=1);

namespace App\Casts;

use App\Models\Currency;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class BaseCurrency implements CastsAttributes
{
    public function __construct(
        public ?string $rate_to_base = null
    ) { }

    /**
     * Cast the given value to user's display currency
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        
        // we have a rate, let's use it to reverse the conversion
        if (!empty($this->rate_to_base) && array_key_exists($this->rate_to_base, $attributes) && $attributes[$this->rate_to_base] != 0) {

            $value = $value * (1 / $attributes[$this->rate_to_base]);
        }

        return (float) $value;
    }

    /**
     * Prepare the given value for storage in base currency
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // skip converting if we already converted
        if (!empty($this->rate_to_base) && array_key_exists($this->rate_to_base, $attributes)) {
            
            return $value;
        }

        // for market data and transactions the `currency` attribute is available...
        // but for dividends and other types, need to make sure `market_data` is loaded
        if (is_null($model?->currency)) {

            $model->loadMarketData();
        }

        return Currency::convert(
            $value,
            $model?->currency ?? $model->market_data?->currency,
            config('investbrain.base_currency'),
            $model?->date
        );
    }
}
