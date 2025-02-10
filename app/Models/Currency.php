<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
     * Convert from provided to base currency
     */
    public static function toBaseCurrency(?float $value, string $from)
    {
        if ($from != config('investbrain.base_currency')) {
            $value = Currency::convert($value, $from);
        }

        return round($value, 3);
    }

    /**
     * Convert from base to user's preferred currency
     */
    public static function toDisplayCurrency(?float $value)
    {
        //
        if (auth()->user()->getCurrency() != config('investbrain.base_currency')) {
            $value = Currency::convert($value, config('investbrain.base_currency'), auth()->user()->getCurrency());
        }

        return round($value, 3);
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
        $from = self::where('currency', $from)->select('rate')->firstOrFail();
        $rate_to_base = 1 / $from->rate;

        // get value in base currency
        $base_currency_value = $value * $rate_to_base;

        // get to rate
        if (empty($to)) {
            $to = config('investbrain.base_currency');
        }
        $to = self::where('currency', $to)->select('rate')->firstOrFail();

        return $base_currency_value * $to->rate;
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
            if (array_key_exists($currency, config('investbrain.currency_aliases'))) {
                $updates[] = [
                    'label' => config('investbrain.currency_aliases.'.$currency.'.label'),
                    'currency' => config('investbrain.currency_aliases.'.$currency.'.currency'),
                    'rate' => $rate * config('investbrain.currency_aliases.'.$currency.'.adjustment'),
                    'is_alias' => true,
                ];
            }

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
