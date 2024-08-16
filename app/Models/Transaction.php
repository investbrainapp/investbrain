<?php

namespace App\Models;

use App\Models\MarketData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'symbol',
        'date',
        'portfolio_id',
        'transaction_type',
        'quantity',
        'cost_basis',
        'sale_price',
        'split'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'datetime',
        'first_date' => 'datetime',
        'last_date' => 'datetime',
        'split' => 'boolean',
    ];

    /**
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($transaction) {

            // if sale, move cost basis to sale price 
            if ($transaction->transaction_type == 'SELL') {

                $transaction->cost_basis = $transaction->holding->average_cost_basis ?? $transaction->cost_basis;
            }
        });

        static::saved(function ($transaction) {

            // static::syncHolding($transaction);
        });

        static::deleted(function ($transaction) {

            // static::syncHolding($transaction);
        });
    }

    public static function syncHolding($transaction) {
        // get the holding for a symbol and portfolio (or create one)
        $holding = Holding::firstOrNew([
            'portfolio_id' => $transaction->portfolio_id,
            'symbol' => $transaction->symbol
        ], [
            'portfolio_id' => $transaction->portfolio_id,
            'symbol' => $transaction->symbol,
            'quantity' => $transaction->quantity,
            'average_cost_basis' => $transaction->cost_basis,
            'total_cost_basis' => $transaction->quantity * $transaction->cost_basis,
        ]);

        // pull existing transaction data
        $query = self::where([
            'portfolio_id' => $transaction->portfolio_id,
            'symbol' => $transaction->symbol,
        ])->selectRaw('SUM(CASE WHEN transaction_type = "BUY" THEN quantity ELSE 0 END) AS `qty_purchases`')
        ->selectRaw('SUM(CASE WHEN transaction_type = "SELL" THEN quantity ELSE 0 END) AS `qty_sales`')
        ->selectRaw('SUM(CASE WHEN transaction_type = "BUY" THEN (quantity * cost_basis) ELSE 0 END) AS `cost_basis`')
        ->selectRaw('SUM(CASE WHEN transaction_type = "SELL" THEN ((sale_price - cost_basis) * quantity) ELSE 0 END) AS `realized_gains`')
        ->first();

        $total_quantity = $query->qty_purchases - $query->qty_sales;
        $average_cost_basis = $query->qty_purchases > 0 
                                ? $query->cost_basis / $query->qty_purchases 
                                : 0;

        // update holding
        $holding->fill([
            'quantity' => $total_quantity,
            'average_cost_basis' => $average_cost_basis,
            'total_cost_basis' => $total_quantity * $average_cost_basis,
            'realized_gain_loss_dollars' => $query->realized_gains,
        ]);

        $holding->save();

        // load market data while we're here
        $transaction->refreshMarketData();

        // sync dividends to holding
        $transaction->syncDividendsToHolding();
    }

    public function setSymbolAttribute($value) 
    {
        $this->attributes['symbol'] = strtoupper($value);
    }

    /**
     * get market data for transaction
     *
     * @return void
     */
    public function market_data()
    {
        return $this->hasOne(MarketData::class, 'symbol', 'symbol');
    }

    /**
     * get portfolio for transaction
     *
     * @return void
     */
    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
    
    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('portfolio_id', $portfolio);
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeMyTransactions()
    {
        return $this->whereHas('portfolio', function ($query) {
            return $query->whereRelation('users', 'id', auth()->user()->id);
        });
    }

    public function refreshMarketData() 
    {
        return MarketData::getMarketData($this->attributes['symbol']);
    }
    
    public function syncDividendsToHolding() 
    {
        return Dividend::syncHoldings(['symbol' => $this->attributes['symbol']]);
    }

    public function refreshDividends() 
    {
        return Dividend::getDividendData($this->attributes['symbol']);
    }
}