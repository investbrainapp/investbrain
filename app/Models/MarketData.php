<?php

declare(strict_types=1);

namespace App\Models;

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
        'dividend_yield',
        'meta_data',
    ];

    protected $casts = [
        'last_dividend_date' => 'datetime',
        'market_value' => 'float',
        'fifty_two_week_high' => 'float',
        'fifty_two_week_low' => 'float',
        'forward_pe' => 'float',
        'trailing_pe' => 'float',
        'market_cap' => 'float',
        'book_value' => 'float',
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

    /**
     * Ensure market values are saved in the base currency
     */
    protected function marketValue(): Attribute
    {
        return Attribute::make(
            // convert to base currency
            set: function ($value) {
                if ($this->attributes['currency'] != config('investbrain.base_currency')) {
                    return Currency::convert($value, $this->attributes['currency']);
                }

                return $value;
            },
            // display in user's preferred currency
            get: function ($value) {
                if (auth()->user()->getCurrency() != config('investbrain.base_currency')) {
                    return Currency::convert($value, $this->attributes['currency'], auth()->user()->getCurrency());
                }

                return $value;
            },
        );
    }

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
