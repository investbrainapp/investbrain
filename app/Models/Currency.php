<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Support\Arr;
use App\Models\CurrencyRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Database\Eloquent\Model;
use Investbrain\Frankfurter\Frankfurter;
use Illuminate\Database\Eloquent\Builder;

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
            'updated_at' => 'datetime'
        ];
    }

    public static function forHumans(int|float $number, ?string $currency = null, ?string $locale = null): string
    {
        $symbol = Number::currencySymbol($currency, $locale);

        return $symbol.Number::forHumans($number);
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

        // Get rate
        [$from, $to] = [
            cache()->remember($from.'_rate_'.$date, 10, function () use ($from, $date) {
                return CurrencyRate::historic($from, $date);
            }),
            cache()->remember($to.'_rate_'.$date, 10, function () use ($to, $date) {
                return CurrencyRate::historic($to, $date);
            }),
        ];

        // get from rate
        $rate_to_base = 1 / $from;

        // get value in base currency
        $base_currency_value = $value * $rate_to_base;

        return (float) $base_currency_value * $to;
    }
}
