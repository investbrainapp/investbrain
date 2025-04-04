<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Investbrain\Frankfurter\Frankfurter;

class CurrencyRate extends Model
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
        'date',
        'currency',
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
            'rate' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function current(string $currency): float
    {
        return (float) self::historic($currency);
    }

    /**
     * Get historic rate for symbol
     */
    public static function historic(string $currency, mixed $date = null): float
    {
        // No need to convert
        if ($currency === config('investbrain.base_currency')) {

            return 1;
        }

        // If we don't need historic, let's use current rate
        if (empty($date)) {

            $date = now();
        }

        // Make sure we have a Carbon date
        $date = Carbon::parse($date);

        // Handle aliases
        [$currency, $adjustment] = self::getCurrencyAliasAdjustments($currency);

        // Get or create historic rate
        $rate = self::select('rate')
            ->whereDate('date', $date->toDateString())
            ->where(['currency' => $currency])
            ->firstOr(function () use ($date, $currency) {

                $currencies = Currency::all()->pluck('currency')->toArray();

                $rates = Frankfurter::setSymbols($currencies)->historical($date);

                $date = Arr::get($rates, 'date');

                $updates = Arr::map(Arr::get($rates, 'rates', []), function ($rate, $curr) use ($date) {

                    return [
                        'currency' => $curr,
                        'date' => $date,
                        'rate' => $rate,
                        'updated_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];
                });

                // todo: queue this
                CurrencyRate::insertOrIgnore($updates);

                $temp = CurrencyRate::make();

                return $temp->fill(Arr::first($updates, fn ($update) => $update['currency'] == $currency) ?? ['rate' => 1]);
            });

        return (float) $rate->rate * $adjustment;
    }

    /**
     * Get rates for range of dates
     *
     * @return array<string, float>
     */
    public static function timeSeriesRates(string $currency, string|\DateTime $start, mixed $end = null): array
    {
        // No need to send network request - just generate 1s
        if ($currency === config('investbrain.base_currency')) {
            $period = CarbonPeriod::create($start, $end);

            $dateRange = [];
            foreach ($period as $date) {
                $dateRange[$date->toDateString()] = 1;
            }

            return $dateRange;
        }

        [$currency, $adjustment] = self::getCurrencyAliasAdjustments($currency);

        $currencies = Currency::all()->pluck('currency')->toArray();
        $rates = Frankfurter::setSymbols($currencies)->timeSeries($start, $end);

        $rates = Arr::get($rates, 'rates', []);

        $updates = [];

        // todo: need to fill in weekend rates

        // loop through each date
        foreach ($rates as $date => $currencies) {

            // loop through each rate
            foreach ($currencies as $curr => $rate) {

                // add to updates
                $updates[] = [
                    'currency' => $curr,
                    'date' => $date,
                    'rate' => $rate,
                    'updated_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                ];
            }
        }

        // todo: queue this
        CurrencyRate::insertOrIgnore($updates);

        return collect($updates)
            ->whereBetween('date', [$start, $end ?? now()])
            ->where('currency', $currency)
            ->mapWithKeys(fn ($rate) => [
                $rate['date'] => $rate['rate'] * $adjustment,
            ])
            ->toArray();
    }

    public static function refreshCurrencyData($force = false): void
    {
        $currencies = Currency::all()->pluck('currency')->toArray();

        $rates = Frankfurter::setBaseCurrency(config('investbrain.base_currency'))
            ->setSymbols($currencies)
            ->latest();

        $updates = [];
        foreach (Arr::get($rates, 'rates', []) as $currency => $rate) {

            // update currency
            $updates[] = [
                'date' => now()->toDateString(),
                'currency' => $currency,
                'rate' => $rate,
            ];
        }

        if (! empty($updates)) {
            CurrencyRate::upsert($updates, ['currency', 'date'], ['rate']);
        }
    }

    protected static function getCurrencyAliasAdjustments($currency)
    {
        $adjustment = 1;

        if (array_key_exists($currency, config('investbrain.currency_aliases', []))) {

            $config = config('investbrain.currency_aliases.'.$currency);

            $adjustment = $config['adjustment'];
            $currency = $config['alias_of'];
        }

        return [$currency, $adjustment];
    }
}
