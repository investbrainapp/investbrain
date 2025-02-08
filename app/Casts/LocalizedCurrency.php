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
        if (auth()->user()->getCurrency() != config('investbrain.base_currency')) {
            $value = Currency::convert($value, config('investbrain.base_currency'), auth()->user()->getCurrency());
        }

        return round($value, 2);
    }

    /**
     * Prepare the given value for storage in base currency
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($attributes['currency'] != config('investbrain.base_currency')) {
            $value = Currency::convert($value, $attributes['currency']);
        }

        return round($value, 2);
    }
}
