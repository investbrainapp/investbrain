<?php

declare(strict_types=1);

use App\Models\Currency;
use Illuminate\Support\Number;

/**
 * Converts from base currency to user's preferred currency
 * */
if (! function_exists('currency')) {

    function currency($value, $to = null): string
    {

        $value = Currency::convert($value, $to ?? auth()->user()->getCurrency());

        return Number::currency($value);
    }
}
