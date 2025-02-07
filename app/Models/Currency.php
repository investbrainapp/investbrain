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

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'currency',
        'label',
        'rate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Converts between supported currencies
     *
     * @param  string|null  $to  (defaults to base currency)
     */
    public static function convert(?float $value, string $from, ?string $to = null): float
    {
        if (empty($value)) {
            return 0;
        }

        // get from rate
        $from = self::where('currency', $from)->firstOrFail();
        $rate_to_base = 1 / $from->rate;

        // get value in base currency
        $base_currency_value = $value * $rate_to_base;

        // get to rate
        if (empty($to)) {
            $to = config('investbrain.base_currency');
        }
        $to = self::where('currency', $to)->firstOrFail();

        return $base_currency_value * $to->rate;
    }

    public static function refreshCurrencyData($force = false): void
    {

        $query = self::query();

        if (! $force) {
            $query->whereNull('updated_at')->orWhere('updated_at', '<=', now()->subMinutes(60));
        }

        $currencies = $query->get()->keyBy('currency');

        $rates = Frankfurter::setBaseCurrency(config('investbrain.base_currency'))
            ->setSymbols(array_keys($currencies->all()))
            ->latest();

        $updates = [];
        foreach (Arr::get($rates, 'rates', []) as $currency => $rate) {

            // update cent currencies
            $cents = [
                'GBP' => ['currency' => 'GBX', 'label' => 'British Sterling Pence'],
                'ZAR' => ['currency' => 'ZAC', 'label' => 'South Africa Rand Cent'],
            ];
            if (array_key_exists($currency, $cents)) {
                $updates[] = [
                    'label' => $cents[$currency]['label'],
                    'currency' => $cents[$currency]['currency'],
                    'rate' => $rate * 100,
                ];
            }

            // update currency
            $updates[] = [
                'label' => $currencies->get($currency)->label,
                'currency' => $currency,
                'rate' => $rate,
            ];
        }

        if (! empty($updates)) {
            Currency::upsert($updates, ['currency'], ['rate']);
        }
    }
}
