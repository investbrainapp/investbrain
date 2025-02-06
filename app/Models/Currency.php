<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Investbrain\Frankfurter\Frankfurter;

class Currency extends Model
{
    protected $hidden = [];

    protected $primaryKey = 'currency';

    protected $keyType = 'string';

    public static function refreshCurrencyData($force = false): void
    {

        $query = self::query();

        if (! $force) {
            $query->whereNull('updated_at')->orWhere('updated_at', '<=', now()->subMinutes(60));
        }

        $currencies = $query->get()->keyBy('currency');

        $rates = Frankfurter::setSymbols(array_keys($currencies->all()))->latest();

        $updates = [];
        foreach (Arr::get($rates, 'rates', []) as $currency => $rate) {

            $updates[] = [
                'label' => $currencies->get($currency)->label,
                'currency' => $currency,
                'rate_to_usd' => $rate,
            ];
        }

        if (! empty($updates)) {
            Currency::upsert($updates, ['currency'], ['rate_to_usd']);
        }
    }
}
