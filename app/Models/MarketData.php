<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\CopyToBaseCurrency;
use App\Casts\BaseCurrency;
use App\Interfaces\MarketData\MarketDataInterface;
use App\Traits\WithBaseCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Pipeline;

class MarketData extends Model
{
    use HasFactory, WithBaseCurrency;

    protected $primaryKey = 'symbol';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'symbol',
        'name',
        'currency',
        'market_value',
        'market_value_base',
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
        'market_value' => 'float',
        'market_value_base' => BaseCurrency::class,
        'fifty_two_week_high' => 'float',
        'fifty_two_week_low' => 'float',
        'forward_pe' => 'float',
        'trailing_pe' => 'float',
        'market_cap' => 'integer',
        'book_value' => 'float',
        'last_dividend_date' => 'datetime',
        'last_dividend_amount' => 'float',
        'dividend_yield' => 'float',
        'meta_data' => 'json',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($market_data) {

            $market_data = Pipeline::send($market_data)
                ->through([
                    CopyToBaseCurrency::class,
                ])
                ->then(fn (MarketData $market_data) => $market_data);
        });
    }

    public function holdings()
    {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public static function getMarketData($symbol, $force = false): self
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
