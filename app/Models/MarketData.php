<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LocalizedCurrency;
use App\Interfaces\MarketData\MarketDataInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketData extends Model
{
    use HasFactory;

    protected $primaryKey = 'symbol';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'symbol',
        'name',
        'currency',
        'market_value',
        'fifty_two_week_high',
        'fifty_two_week_low',
        'forward_pe',
        'trailing_pe',
        'market_cap',
        'book_value',
        'last_dividend_date',
        'last_dividend_amount',
        'dividend_yield',
        'meta_data',
    ];

    protected $casts = [
        'market_value' => LocalizedCurrency::class,
        'fifty_two_week_high' => LocalizedCurrency::class,
        'fifty_two_week_low' => LocalizedCurrency::class,
        'forward_pe' => 'float',
        'trailing_pe' => 'float',
        'market_cap' => LocalizedCurrency::class,
        'book_value' => LocalizedCurrency::class,
        'last_dividend_date' => 'datetime',
        'last_dividend_amount' => LocalizedCurrency::class,
        'dividend_yield' => 'float',
        'meta_data' => 'json',
    ];

    public function holdings()
    {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    // /**
    //  * Ensure market values are saved in the base currency
    //  */
    // protected function marketValue(): Attribute
    // {
    //     return Attribute::make(
    //         set: fn ($value) => Currency::toBaseCurrency($value, from: $this->attributes['currency']),
    //         get: fn ($value) => Currency::toDisplayCurrency($value)
    //     );
    // }

    public static function getMarketData($symbol, $force = false)
    {
        $market_data = self::firstOrNew([
            'symbol' => $symbol,
        ]);

        // check if new or stale
        if (
            $force
            || ! $market_data->exists
            || is_null($market_data->updated_at)
            || $market_data->updated_at->diffInMinutes(now()) >= config('investbrain.refresh')
        ) {

            // get quote
            $quote = app(MarketDataInterface::class)->quote($symbol);

            // fill data
            $market_data->fill($quote->toArray());

            // save with timestamps updated
            $market_data->touch();
        }

        return $market_data;
    }
}
