<?php

namespace App\Models;

use App\Models\Dividend;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Holding extends Model
{
    use HasFactory;
    use HasUuids;

    protected $with = ['market_data'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'splits_synced_at' => 'datetime',
        'dividends_synced_at' => 'datetime',
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
        return $this->hasMany(Transaction::class, 'symbol', 'symbol');
    }

    /**
     * Related dividends for holding
     *
     * @return void
     */
    public function dividends() 
    {
        return $this->hasMany(Dividend::class, 'symbol', 'symbol');
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
        return $this->hasMany(Split::class, 'symbol', 'symbol');
    }

    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('portfolio_id', $portfolio);
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
            ->selectRaw('COALESCE((@total_gain_dollars / @sum_total_cost_basis) * 100,0) AS total_gain_percent')
            ->join('market_data', 'market_data.symbol', 'holdings.symbol');
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    // public function refreshDividends() 
    // {
    //     return Dividend::getDividendData($this->attributes['symbol']);
    // }
}

    