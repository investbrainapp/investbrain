<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
        'is_alias',
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
            'is_alias' => 'boolean',
        ];
    }

    public function scopeWithoutAliases($query): Builder
    {
        return $query->where(function ($query) {
            return $query->whereNull('is_alias')->orWhere('is_alias', false);
        });
    }

    /**
     * Converts between supported currencies
     *
     * @param  string|null  $to  (defaults to base currency)
     */
    public static function convert(?float $value, string $from, ?string $to = null, mixed $date = null): float
    {
        if (empty($value)) {
            return 0;
        }

        // Assume converting to base
        if (empty($to)) {
            $to = config('investbrain.base_currency');
        }

        // Needs historic conversion
        if (! is_null($date) && ! Carbon::parse($date)->isToday()) {
            $rate = self::historicRate($from, $to, $date);

            return (float) $value * $rate;
        }

        // Get rates from cache
        [$from, $to] = [
            cache()->remember($from.'_rate', 10, function () use ($from) {
                return self::where('currency', $from)->select('rate')->firstOrFail();
            }),
            cache()->remember($to.'_rate', 10, function () use ($to) {
                return self::where('currency', $to)->select('rate')->firstOrFail();
            }),
        ];

        // get from rate
        $rate_to_base = 1 / $from->rate;

        // get value in base currency
        $base_currency_value = $value * $rate_to_base;

        return (float) $base_currency_value * $to->rate;
    }

    public static function historicRate(string $from, ?string $to, mixed $date = null): float
    {
        // Assume converting to base
        if (empty($to)) {
            $to = config('investbrain.base_currency');
        }

        // No conversion required
        if ($from === $to) {
            return 1;
        }

        // If we don't need historic, let's use current rate
        if (is_null($date) || Carbon::parse($date)->isToday()) {
            return self::convert(1, $from, $to);
        }

        // Make sure we have a Carbon date
        $date = Carbon::parse($date);

        return (float) cache()->remember("{$from}_{$to}_rate_{$date->toDateString()}", 10, function () use ($from, $to, $date) {
            $rate = Frankfurter::setBaseCurrency($from)->setSymbols($to)->historical($date);

            return Arr::get($rate, "rates.{$to}");
        });
    }

    public static function timeSeriesRates(string $from, ?string $to, string|\DateTime $start, mixed $end = null): array
    {
        // get to rate
        if (empty($to)) {
            $to = config('investbrain.base_currency');
        }

        // no need to send network request - just generate 1s
        if ($from === $to) {
            $period = CarbonPeriod::create($start, $end);

            $dateRange = [];
            foreach ($period as $date) {
                $dateRange[$date->format('Y-m-d')] = 1;
            }

            return $dateRange;
        }

        $rates = Frankfurter::setBaseCurrency($from)->setSymbols($to)->timeSeries($start, $end);
        $rates = Arr::get($rates, 'rates', []);
        $rates = Arr::map($rates, fn ($value) => $value[$to]);

        return $rates;
    }

    public static function refreshCurrencyData($force = false): void
    {

        $query = self::withoutAliases();

        if (! $force) {
            $query->where(function ($query) {
                return $query->whereNull('updated_at')->orWhere('updated_at', '<=', now()->subMinutes(60));
            });
        }

        $currencies = $query->get()->keyBy('currency');

        $rates = Frankfurter::setBaseCurrency(config('investbrain.base_currency'))
            ->setSymbols(array_keys($currencies->all()))
            ->latest();

        $updates = [];
        foreach (Arr::get($rates, 'rates', []) as $currency => $rate) {

            // handle currency aliases
            collect(config('investbrain.currency_aliases', []))
                ->where('alias_of', $currency)
                ->each(function ($value, $alias) use ($rate, &$updates) {
                    $updates[] = [
                        'label' => $value['label'],
                        'currency' => $alias,
                        'rate' => $rate * $value['adjustment'],
                        'is_alias' => true,
                    ];
                });

            // update currency
            $updates[] = [
                'label' => $currencies->get($currency)->label,
                'currency' => $currency,
                'rate' => $rate,
                'is_alias' => null,
            ];
        }

        if (! empty($updates)) {
            Currency::upsert($updates, ['currency'], ['rate']);
        }
    }
}
