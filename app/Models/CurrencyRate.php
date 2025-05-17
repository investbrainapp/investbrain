<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\QueuedCurrencyRateInsertJob;
use Carbon\CarbonInterface;
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
            'date' => 'date',
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

                // persist
                self::chunkInsert($updates);

                return new CurrencyRate(Arr::first($updates, fn ($update) => $update['currency'] == $currency) ?? ['rate' => 1]);
            });

        return (float) $rate->rate * $adjustment;
    }

    /**
     * Get rates for range of dates
     *
     * @return array<string, float>
     */
    public static function timeSeriesRates(string|array|null $currency = null, mixed $start = null, mixed $end = null): array
    {
        if (empty($start)) {
            return [];
        }

        $end = $end ?? now();

        $period = CarbonPeriod::create($start, $end);

        // No need to send network request - just generate 1s
        if ($currency === config('investbrain.base_currency')) {

            $dateRange = [];
            foreach ($period as $date) {
                $dateRange[$date->toDateString()] = 1;
            }

            return $dateRange;
        }

        if (is_array($currency)) {

            foreach ($currency as $curr) {

                dispatch(fn () => self::timeSeriesRates($curr, $start, $end));
            }

            return [];
        }

        // handle currency alias
        if (! empty($currency)) {

            [$currency, $adjustment] = self::getCurrencyAliasAdjustments($currency);

        } else {

            $currency = Currency::all()->pluck('currency')->toArray();
        }

        // get rates
        $rates = Frankfurter::setSymbols($currency)->timeSeries($period->first(), $period->last());

        $rates = collect(Arr::get($rates, 'rates', []))->sortKeys()->toArray();

        $datesOnly = array_keys($rates);

        // loop through each date
        $updates = [];
        foreach ($period as $date) {

            $lookupDate = self::getNearestPastDate($date, $datesOnly, $rates);

            if (is_null($lookupDate)) {
                continue;
            }

            // loop through each rate
            foreach ($rates[$lookupDate->toDateString()] as $curr => $rate) {

                // add to updates
                $updates[] = [
                    'currency' => $curr,
                    'date' => $date->toDateString(),
                    'rate' => $rate,
                    'updated_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                ];
            }
        }

        // persist
        self::chunkInsert($updates);

        if (is_string($currency)) {

            return collect($updates)
                ->whereBetween('date', [$start, $end ?? now()])
                ->where('currency', $currency)
                ->mapWithKeys(fn ($rate) => [
                    $rate['date'] => $rate['rate'] * ($adjustment ?? 1),
                ])
                ->toArray();
        }

        return [];
    }

    private static function getNearestPastDate(CarbonInterface $date, array $datesOnly, array $rates): ?CarbonInterface
    {

        // if no dates, nothing to do...
        if (empty($datesOnly)) {

            return null;
        }

        $mutableDate = $date->copy();
        $weekAgo = $date->copy()->subWeek();
        $firstDate = Carbon::parse($datesOnly[0]);

        // get rates or find closest valid rate (handles missing weekend rates)
        while (! isset($rates[$mutableDate->toDateString()])) {

            // prevent runaway infinite loops
            if ($mutableDate->lessThan($weekAgo)) {

                return null;
            }

            // is this the start of a range that falls on a weekend?
            if ($mutableDate->lessThan($firstDate)) {

                return $firstDate;
            }

            // try the day before then
            $mutableDate = $mutableDate->subDay();
        }

        return $mutableDate;
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

        // nothing to update
        if (empty($updates)) {
            return;
        }

        if ($force) {

            // force overwrite existing rates
            CurrencyRate::upsert($updates, ['currency', 'date'], ['rate']);
        } else {

            // only insert new rates
            CurrencyRate::insertOrIgnore($updates);
        }
    }

    public static function chunkInsert(array $updates): void
    {

        foreach (array_chunk($updates, 500) as $chunk) {

            QueuedCurrencyRateInsertJob::dispatch($chunk);
        }
    }

    protected static function getCurrencyAliasAdjustments(string $currency)
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
