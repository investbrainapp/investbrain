<?php

namespace App\Models;

use App\Models\Split;
use App\Models\Dividend;
use App\Models\Portfolio;
use App\Models\MarketData;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
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
    ];

    protected $casts = [
        'splits_synced_at' => 'datetime',
        'first_transaction_date' => 'datetime'
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
        return $this->hasManyThrough(Transaction::class, Portfolio::class, 'id', 'portfolio_id', 'portfolio_id', 'id')->orderBy('date', 'DESC');
    }

    /**
     * Related dividends for holding
     *
     * @return void
     */
    public function dividends() 
    {
        return $this->hasMany(Dividend::class, 'symbol', 'symbol')
                ->select(['dividends.symbol','dividends.date','dividends.dividend_amount'])
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
                ->groupBy(['dividends.symbol','dividends.date','dividends.dividend_amount'])
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
        return $query->withAggregate('market_data', 'name')
                    ->withAggregate('market_data', 'market_value')
                    ->withAggregate('market_data', 'fifty_two_week_low')
                    ->withAggregate('market_data', 'fifty_two_week_high')
                    ->withAggregate('market_data', 'updated_at')
                    ->join('market_data', 'holdings.symbol', 'market_data.symbol');
    }

    public function scopeWithPerformance($query)
    {
        return $query->selectRaw('COALESCE(market_data.market_value * holdings.quantity, 0) AS total_market_value')
            ->selectRaw('COALESCE((market_data.market_value - holdings.average_cost_basis) * holdings.quantity, 0) AS market_gain_dollars')
            ->selectRaw('COALESCE(((market_data.market_value - holdings.average_cost_basis) / holdings.average_cost_basis), 0) AS market_gain_percent');
    }

    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('holdings.portfolio_id', $portfolio);
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('holdings.symbol', $symbol);
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

    public function scopeWithPortfolioMetrics($query) 
    {
        return $query->selectRaw('COALESCE(SUM(holdings.dividends_earned), 0) AS total_dividends_earned')
                    ->selectRaw('COALESCE(SUM(holdings.realized_gain_dollars), 0) AS realized_gain_dollars')
                    ->selectRaw('COALESCE(SUM(holdings.quantity * market_data.market_value), 0) AS total_market_value')
                    ->selectRaw('COALESCE(SUM(holdings.total_cost_basis), 0) AS total_cost_basis')
                    ->selectRaw('COALESCE(SUM(holdings.quantity * market_data.market_value), 0) - COALESCE(SUM(holdings.total_cost_basis), 0) AS total_gain_dollars')
                    // ->selectRaw('COALESCE((@total_gain_dollars / @sum_total_cost_basis) * 100,0) AS total_gain_percent')
                    ->join('market_data', 'market_data.symbol', '=', 'holdings.symbol');            
    }

    public function syncTransactionsAndDividends()
    {
        // pull existing transaction data
        $query = Transaction::where([
                            'portfolio_id' => $this->portfolio_id,
                            'symbol' => $this->symbol,
                        ])->selectRaw('SUM(CASE WHEN transaction_type = "BUY" THEN quantity ELSE 0 END) AS `qty_purchases`')
                        ->selectRaw('SUM(CASE WHEN transaction_type = "SELL" THEN quantity ELSE 0 END) AS `qty_sales`')
                        ->selectRaw('SUM(CASE WHEN transaction_type = "BUY" THEN (quantity * cost_basis) ELSE 0 END) AS `cost_basis`')
                        ->selectRaw('SUM(CASE WHEN transaction_type = "SELL" THEN ((sale_price - cost_basis) * quantity) ELSE 0 END) AS `realized_gains`')
                        ->first();

        $total_quantity = round($query->qty_purchases - $query->qty_sales, 5);

        $average_cost_basis = (
                                $query->qty_purchases > 0 
                                && $total_quantity > 0
                              )
                                    ? $query->cost_basis / $query->qty_purchases 
                                    : 0;

        // pull dividend data joined with holdings/transactions
        $dividends = Dividend::select('holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount')
                            ->selectRaw('
                                (COALESCE(CASE WHEN transactions.transaction_type = "BUY" 
                                    AND date(transactions.date) <= date(dividends.date) 
                                    THEN transactions.quantity ELSE 0 END, 0)
                                - COALESCE(CASE WHEN transactions.transaction_type = "SELL" 
                                    AND date(transactions.date) <= date(dividends.date) 
                                    THEN transactions.quantity ELSE 0 END, 0))
                                * dividends.dividend_amount
                                    AS total_received
                            ')
                            ->join('transactions', 'transactions.symbol', 'dividends.symbol')
                            ->join('holdings', 'transactions.portfolio_id', 'holdings.portfolio_id')
                            ->where('dividends.symbol', $this->symbol)
                            ->where('transactions.portfolio_id', $this->portfolio_id)
                            ->groupBy('holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount', 'total_received')
                            ->get();

        // update holding
        $this->fill([
            'quantity' => $total_quantity,
            'average_cost_basis' => $average_cost_basis,
            'total_cost_basis' => $total_quantity * $average_cost_basis,
            'realized_gain_dollars' => $query->realized_gains,
            'dividends_earned' => $dividends->sum('total_received')
        ]);

        $this->save();
    }

    public function dailyPerformance(
        \Illuminate\Support\Carbon $start_date = null, 
        \Illuminate\Support\Carbon $end_date = null, 
    ) {
        if ($start_date == null) $start_date = now();
        if ($end_date == null) $end_date = now();

        $date_interval = "DATE_ADD(date, INTERVAL 1 DAY)";

        if (config('database.default') === 'sqlite') {
            
            $date_interval = "date(date, '+1 day')";
        }

        return DB::table(DB::raw("(
                WITH RECURSIVE date_series AS (
                    SELECT '{$start_date->format('Y-m-d')}' AS date
                    UNION ALL
                    SELECT $date_interval
                    FROM date_series
                    WHERE date < '{$end_date->format('Y-m-d')}'
                )
                SELECT date_series.date
                FROM date_series
            ) as date_series")
        )
        ->select([
            'date_series.date',
            DB::raw("
                COALESCE(SUM(CASE WHEN transactions.transaction_type = 'BUY' THEN transactions.quantity ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN transactions.transaction_type = 'SELL' THEN transactions.quantity ELSE 0 END), 0) AS `owned`
            "),
            DB::raw("
               COALESCE(CASE 
                    WHEN (
                        COALESCE(SUM(CASE WHEN transactions.transaction_type = 'BUY' THEN transactions.quantity ELSE 0 END), 0) - 
                        COALESCE(SUM(CASE WHEN transactions.transaction_type = 'SELL' THEN transactions.quantity ELSE 0 END), 0)
                    ) = 0 THEN 0
                    ELSE SUM(CASE 
                        WHEN transactions.transaction_type = 'BUY' THEN transactions.quantity * transactions.cost_basis 
                        ELSE 0 
                    END)
                END, 0) AS cost_basis
            "),
            DB::raw("COALESCE(SUM(CASE WHEN transaction_type = 'SELL' THEN ((sale_price - cost_basis) * quantity) ELSE 0 END), 0) AS `realized_gains`")
        ])
        ->leftJoin('transactions', function ($join) {
            $join->on(DB::raw('DATE(transactions.date)'), '<=', 'date_series.date')
                ->where('transactions.symbol', '=', $this->symbol)
                ->where('transactions.portfolio_id', '=', $this->portfolio_id);
        })
        ->groupBy('date_series.date')
        ->orderBy('date_series.date')
        ->get()
        ->keyBy('date');
    }
}