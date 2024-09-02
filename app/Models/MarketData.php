<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Interfaces\MarketData\MarketDataInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarketData extends Model
{
    use HasFactory;

    protected $primaryKey = 'symbol';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'symbol',
        'name',
        'market_value',
        'fifty_two_week_high',
        'fifty_two_week_low',
        'forward_pe',
        'trailing_pe',
        'market_cap',
        'book_value',
        'last_dividend_date',
        'dividend_yield'
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
        'dividend_yield' => 'float'
    ];

    public function holdings() 
    {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public static function getMarketData($symbol) 
    {
        $market_data = self::firstOrNew([
            'symbol' => $symbol
        ]);

        // check if new or stale
        if (
            !$market_data->exists
            || is_null($market_data->updated_at)
            || $market_data->updated_at->diffInMinutes(now()) >= config('market_data.refresh')
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