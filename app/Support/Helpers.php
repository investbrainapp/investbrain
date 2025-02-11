<?php

declare(strict_types=1);

use App\Models\Currency;
use Illuminate\Support\Number;

/**
 * Returns currency in user's preferred currency
 * */
if (! function_exists('currency')) {

    function currency($value): string
    {
        $value = Currency::toDisplayCurrency($value);

        return Number::currency($value);
    }
}
