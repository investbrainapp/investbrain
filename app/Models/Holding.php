<?php

namespace App\Models;

use App\Models\Split;
use App\Models\Dividend;
use App\Models\Portfolio;
use App\Models\MarketData;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Holding extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'portfolio_id',
        'symbol',
        'quantity',
        'average_cost_basis',
        'total_cost_basis',
        'realized_gain_dollars',
        'dividends_earned',
        'splits_synced_at',
        'dividends_synced_at'
    ];

    protected $casts = [
        'splits_synced_at' => 'datetime',
        'dividends_synced_at' => 'datetime',
    ];

    protected $attributes = [
        'realized_gain_dollars' => 0,
        'dividends_earned' => 0,
    ];

    /**
     * Market data for holding
     *
     * @return void
     */
    public function market_data() 
    {
        return $this->hasOne(MarketData::class, 'symbol', 'symbol');
    }

    /**
     * Related transactions for holding
     *
     * @return void
     */
    public function transactions() 
    {
        return $this->hasMany(Transaction::class, 'symbol', 'symbol')
                    ->where('transactions.portfolio_id', $this->portfolio_id);
    }

    /**
     * Related dividends for holding
     *
     * @return void
     */
    public function dividends() 
    {
        return $this->hasMany(Dividend::class, 'symbol', 'symbol')
                ->select([
                    'dividends.symbol',
                    'dividends.date',
                    'dividends.dividend_amount',
                ])
                ->selectRaw("SUM(
                    CASE WHEN transaction_type = 'BUY' 
                        AND transactions.symbol = dividends.symbol 
                        AND transactions.portfolio_id = '$this->portfolio_id'
                        AND dividends.date >= transactions.date 
                    THEN transactions.quantity
                    ELSE 0 END
                ) AS purchased")
                ->selectRaw("SUM(
                    CASE WHEN transaction_type = 'SELL'
                        AND transactions.symbol = dividends.symbol 
                        AND transactions.portfolio_id = '$this->portfolio_id' 
                        AND dividends.date >= transactions.date 
                    THEN transactions.quantity
                    ELSE 0 END
                ) AS sold")
                ->join('transactions', 'transactions.symbol', 'dividends.symbol')
                ->groupBy([
                    'dividends.symbol',
                    'dividends.date',
                    'dividends.dividend_amount',
                ])
                ->orderBy('dividends.date', 'DESC')
                ->where('dividends.date', '>=', function ($query) {
                    $query->selectRaw('min(transactions.date)')
                        ->from('transactions')
                        ->whereRaw("transactions.portfolio_id = '$this->portfolio_id'")
                        ->whereRaw("transactions.symbol = '$this->symbol'");
                });
    }

    /**
     * Related portfolio for holding
     *
     * @return void
     */
    public function portfolio() 
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * Related splits for holding
     *
     * @return void
     */
    public function splits() 
    {
        return $this->hasMany(Split::class, 'symbol', 'symbol')
            ->orderBy('date', 'DESC');
    }

    public function scopeWithMarketData($query)
    {
        $query->withAggregate('market_data', 'name')
                ->withAggregate('market_data', 'market_value')
                ->withAggregate('market_data', 'fifty_two_week_low')
                ->withAggregate('market_data', 'fifty_two_week_high')
                ->withAggregate('market_data', 'updated_at')
                ->join('market_data', 'holdings.symbol', 'market_data.symbol');
    }

    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('portfolio_id', $portfolio);
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeWithoutWishlists($query) {
        return $query->join('portfolios', 'portfolios.id', 'holdings.portfolio_id')
            ->where('portfolios.wishlist', 0);
    }

    public function scopeMyHoldings($query)
    {
        return $query->whereHas('portfolio', function($query) {
            $query->whereRelation('users', 'id', auth()->user()->id);
        });
    }

    public function scopeGetPortfolioMetrics($query) 
    {

        $query->selectRaw('COALESCE(SUM(holdings.dividends_earned),0) AS total_dividends_earned')
            ->selectRaw('COALESCE(SUM(holdings.realized_gain_dollars),0) AS realized_gain_dollars')
            ->selectRaw('@total_market_value:=COALESCE(SUM(holdings.quantity * market_data.market_value),0) AS total_market_value')
            ->selectRaw('@sum_total_cost_basis:=COALESCE(SUM(holdings.total_cost_basis),0) AS total_cost_basis')
            ->selectRaw('@total_gain_dollars:=COALESCE((@total_market_value - @sum_total_cost_basis),0) AS total_gain_dollars')
            // ->selectRaw('COALESCE((@total_gain_dollars / @sum_total_cost_basis) * 100,0) AS total_gain_percent')
            ->join('market_data', 'market_data.symbol', 'holdings.symbol');
    }

    // public function refreshDividends() 
    // {
    //     return Dividend::getDividendData($this->attributes['symbol']);
    // }
}

    