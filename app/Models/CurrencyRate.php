<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\QueuedCurrencyRateInsertJob;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
    public static function timeSeriesRates(string $currency, mixed $start = null, mixed $end = null): array
    {
        if (empty($start)) {
            return [];
        }

        $end = $end ?? now();

        dump('Creating period');

        $period = CarbonPeriod::create($start, $end);

        // No need to send network request - just generate 1s
        if ($currency === config('investbrain.base_currency')) {

            dump('same curr');

            $dateRange = [];
            foreach ($period as $date) {

                $dateRange[$date->toDateString()] = 1;
            }

            return $dateRange;
        }

        dump('diff curr');

        [$currency, $adjustment] = self::getCurrencyAliasAdjustments($currency);

        $currencies = Currency::all()->pluck('currency')->toArray();

        dump('got currencies');

        // call api in chunks
        $rates = [];
        foreach (collect($period)->chunk(500) as $chunk) {

            dump('calling frankf time series');

            $chunkRates = Frankfurter::setSymbols($currencies)->timeSeries($chunk->min(), $chunk->max());

            $rates = array_merge($rates, Arr::get($chunkRates, 'rates', []));
        }

        dump('done with frankf', count($rates));

        // loop through each date
        $updates = [];
        foreach ($period as $date) {

            $lookupDate = self::getNearestPastDate($date, $rates);

            Log::warn($lookupDate, isset($rates[$lookupDate->toDateString()]));

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

        dump('inserting');

        // persist
        self::chunkInsert($updates);

        dump('done');

        return collect($updates)
            ->whereBetween('date', [$start, $end ?? now()])
            ->where('currency', $currency)
            ->mapWithKeys(fn ($rate) => [
                $rate['date'] => $rate['rate'] * $adjustment,
            ])
            ->toArray();
    }

    private static function getNearestPastDate(CarbonInterface $date, array $rates): ?CarbonInterface
    {
        $datesWithRates = array_keys($rates);
        sort($datesWithRates);

        // get rates or find closest valid rate (handles missing weekend rates)
        while (! isset($rates[$date->toDateString()])) {

            // is this the start of a range that falls on a weekend?
            if ($date->lessThan($first_date = Carbon::parse($datesWithRates[0]))) {

                $date = $first_date;
                break;
            }

            // try the day before then
            $date = Carbon::parse($date)->subDay();

            // prevent runaway infinite loops
            if ($date->lessThan($date->copy()->subWeek())) {

                $date = null;
                break;
            }
        }

        return $date;
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

        $chunks = array_chunk($updates, 500);

        foreach ($chunks as $chunk) {

            QueuedCurrencyRateInsertJob::dispatch($chunk);
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
