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
        'portfolio_id',
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

            $transaction->syncToHolding();

            $transaction->refreshMarketData();

            cache()->tags(['metrics', $transaction->portfolio_id])->flush();
        });

        static::deleted(function ($transaction) {

            $transaction->syncToHolding();

            cache()->tags(['metrics', $transaction->portfolio_id])->flush();
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

    public function scopeWithMarketData($query)
    {
        return $query->withAggregate('market_data', 'name')
                    ->withAggregate('market_data', 'market_value')
                    ->withAggregate('market_data', 'fifty_two_week_low')
                    ->withAggregate('market_data', 'fifty_two_week_high')
                    ->withAggregate('market_data', 'updated_at')
                    ->join('market_data', 'transactions.symbol', 'market_data.symbol');
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
            $query->whereHas('users', function ($query) {
                $query->where('id', auth()->id());
            });
        });
    }

    public function refreshMarketData() 
    {
        return MarketData::getMarketData($this->attributes['symbol']);
    }

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
    public function syncToHolding() {

        // if symbol name changed, sync previous symbol too
        if (Arr::has($this->changes, 'symbol')) {

            $temp = new Transaction;
            $temp->symbol = $this->original['symbol'];
            $temp->portfolio_id = $this->portfolio_id;

            $temp->syncToHolding();
        }

        // get the holding for a symbol and portfolio (or create one)
        Holding::firstOrNew([
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol
        ], [
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
            'quantity' => $this->quantity,
            'average_cost_basis' => $this->cost_basis,
            'total_cost_basis' => $this->quantity * $this->cost_basis,
        ])->syncTransactionsAndDividends();
    }
}