<?php

declare(strict_types=1);

use App\Models\Currency;
use Illuminate\Support\Number;

if (! function_exists('currency')) {

    /**
     * By default, will convert from app's base currency to user's preferred display currency
     * */
    function currency($value, $from = null, $to = null, $date = null): string
    {
        $from = $from ?? config('investbrain.base_currency');
        $to = $to ?? auth()->user()->getCurrency();

        $value = Currency::convert($value, $from, $to, $date);

        return Number::currency($value, $to);
    }
}
