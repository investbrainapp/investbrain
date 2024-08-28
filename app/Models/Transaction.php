<?php

namespace App\Models;

use App\Models\MarketData;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'symbol',
        'date',
        'transaction_type',
        'quantity',
        'cost_basis',
        'sale_price'
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'split' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($transaction) {

            if ($transaction->transaction_type == 'SELL') {

                $transaction->ensureCostBasisIsAddedToSale();
            }
        });

        static::saved(function ($transaction) {

            $transaction->syncHolding();

            cache()->tags(['metrics', auth()->user()->id])->flush();
        });

        static::deleted(function ($transaction) {

            $transaction->syncHolding();

            cache()->tags(['metrics', auth()->user()->id])->flush();
        });
    }

    /**
     * Ensure transaction symbol is always upper case
     */
    protected function symbol(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper($value)
        );
    }

    /**
     * Related market data for transaction
     *
     * @return void
     */
    public function market_data()
    {
        return $this->hasOne(MarketData::class, 'symbol', 'symbol');
    }

    /**
     * Related portfolio
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

    public function refreshMarketData() 
    {
        return MarketData::getMarketData($this->attributes['symbol']);
    }
    
    // public function syncDividendsToHolding() 
    // {
    //     return Dividend::syncHoldings(['symbol' => $this->attributes['symbol']]);
    // }

    // public function refreshDividends() 
    // {
    //     return Dividend::getDividendData($this->attributes['symbol']);
    // }

    /**
     * Writes average cost basis to a sale transaction
     *
     * @return Transaction
     */
    public function ensureCostBasisIsAddedToSale()
    {
        $average_cost_basis = Transaction::where([
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
            'transaction_type' => 'BUY',
        ])->whereDate('date', '<=', $this->date)
        ->average('cost_basis');

        $this->cost_basis = $average_cost_basis ?? 0;

        return $this;
    }

    /**
     * Syncs the holding related to this transaction
     *
     * @return void
     */
    public function syncHolding() {

        // sync previous symbol too
        if (Arr::has($this->changes, 'symbol')) {

            $temp = new Transaction;
            $temp->symbol = $this->original['symbol'];
            $temp->portfolio_id = $this->portfolio_id;

            $temp->syncHolding();
        }

        // get the holding for a symbol and portfolio (or create one)
        $holding = Holding::firstOrNew([
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol
        ], [
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
            'quantity' => $this->quantity,
            'average_cost_basis' => $this->cost_basis,
            'total_cost_basis' => $this->quantity * $this->cost_basis,
        ]);

        // pull existing transaction data
        $query = self::where([
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
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
            'realized_gain_dollars' => $query->realized_gains,
        ]);

        $holding->save();

        // load market data while we're here
        $this->refreshMarketData();

        // // sync dividends to holding
        // $this->syncDividendsToHolding();
    }
}