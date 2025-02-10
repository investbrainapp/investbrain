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
        return Currency::toDisplayCurrency($value);
    }

    /**
     * Prepare the given value for storage in base currency
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // if (! isset($model->currency)) {
        //     dump($model);
        // }

        return Currency::toBaseCurrency($value, $attributes['currency']);
    }
}
