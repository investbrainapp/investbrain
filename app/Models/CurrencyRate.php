<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Currency;
use Carbon\CarbonPeriod;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function current(string $currency): float
    {
        return (float) self::historic($currency);
    }

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

        // Get or create historic rate
        return (float) self::where([
                'date' => $date->toDateString(), 
                'currency' => $currency
            ])
            ->select('rate')
            ->firstOr(function () use ($currency, $date) {

                [$currency, $adjustment] = self::getCurrencyAliasAdjustments($currency);

                // grab rate from API
                $rate = Arr::get(Frankfurter::setSymbols($currency)->historical($date), "rates.{$currency}");

                // persist to database
                return self::create([
                    'currency' => $currency,
                    'date' => $date->toDateString(),
                    'rate' => $rate * $adjustment
                ]);
            })->rate;
        
    }

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

        $rates = Frankfurter::setSymbols($currency)->timeSeries($start, $end);
        $rates = Arr::get($rates, 'rates', []);
        $rates = Arr::map($rates, fn ($value) => $value[$currency] * $adjustment);

        return $rates;
    }

    public static function refreshCurrencyData($force = false): void
    {
        $currencies = Currency::get()->keyBy('currency');

        $rates = Frankfurter::setBaseCurrency(config('investbrain.base_currency'))
            ->setSymbols(array_keys($currencies->all()))
            ->latest();

        $updates = [];
        foreach (Arr::get($rates, 'rates', []) as $currency => $rate) {

            // create currency aliases
            collect(config('investbrain.currency_aliases', []))
                ->where('alias_of', $currency)
                ->each(function ($value, $alias) use ($rate, &$updates) {
                    $updates[] = [
                        'date' => now()->toDateString(),
                        'currency' => $alias,
                        'rate' => $rate * $value['adjustment'],
                    ];
                });

            // update currency
            $updates[] = [
                'date' => now()->toDateString(),
                'currency' => $currency,
                'rate' => $rate
            ];
        }

        if (! empty($updates)) {
            CurrencyRate::upsert($updates, ['currency', 'date'], ['rate']);
        }
    }

    protected static function getCurrencyAliasAdjustments($currency)
    {
        $adjustment = null;

        if (array_key_exists($currency, config('investbrain.currency_aliases', []))) {

            $config = config('investbrain.currency_aliases.'.$currency);

            $adjustment = $config['adjustment'];
            $currency = $config['alias_of'];
        }

        return [$currency, $adjustment ?? 1];
    }
}
