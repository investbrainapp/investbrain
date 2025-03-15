<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Portfolio;
use App\Casts\BaseCurrency;
use App\Models\Transaction;
use Illuminate\Support\Arr;
use App\Traits\HasMarketData;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Holding extends Model
{
    use HasFactory;
    use HasMarketData;
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
        'reinvest_dividends',
        'cost_basis_rate',
        'realized_gain_rate',
        'dividends_rate',
    ];

    protected $casts = [
        'reinvest_dividends' => 'boolean',
        'splits_synced_at' => 'datetime',
        'first_transaction_date' => 'datetime',
        'quantity' => 'float',
        'average_cost_basis' => BaseCurrency::class.':cost_basis_rate',
        'total_cost_basis' => BaseCurrency::class.':cost_basis_rate',
        'realized_gain_dollars' => BaseCurrency::class.':realized_gain_rate',
        'dividends_earned' => BaseCurrency::class.':dividends_rate',
        'total_gain_dollars' => 'float',
        'market_gain_dollars' => 'float',
        'total_market_value' => 'float',
        'total_dividends_earned' => 'float',
        'market_data_market_value' => 'float',
        'market_data_fifty_two_week_low' => 'float',
        'market_data_fifty_two_week_high' => 'float',
        'market_gain_percent' => 'float',
    ];

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
            ->select(['dividends.symbol', 'dividends.date', 'dividends.dividend_amount', 'dividends.dividend_amount_base'])
            ->selectRaw("SUM(
                    CASE WHEN transaction_type = 'BUY'
                        AND transactions.symbol = dividends.symbol
                        AND transactions.portfolio_id = '$this->portfolio_id'
                        AND date(dividends.date) >= date(transactions.date)
                    THEN transactions.quantity
                    ELSE 0 END
                ) AS purchased")
            ->selectRaw("SUM(
                    CASE WHEN transaction_type = 'SELL'
                        AND transactions.symbol = dividends.symbol
                        AND transactions.portfolio_id = '$this->portfolio_id'
                        AND date(dividends.date) >= date(transactions.date)
                    THEN transactions.quantity
                    ELSE 0 END
                ) AS sold")
            ->selectRaw("SUM(
                    (CASE WHEN transaction_type = 'BUY'
                        AND transactions.symbol = dividends.symbol
                        AND transactions.portfolio_id = '$this->portfolio_id'
                        AND date(transactions.date) <= date(dividends.date)
                        THEN transactions.quantity ELSE 0 END
                    - CASE WHEN transaction_type = 'SELL'
                        AND transactions.symbol = dividends.symbol
                        AND transactions.portfolio_id = '$this->portfolio_id'
                        AND date(transactions.date) <= date(dividends.date)
                        THEN transactions.quantity ELSE 0 END)
                    * dividends.dividend_amount_base
                ) AS total_received")
            ->selectRaw("CASE 
                WHEN SUM(dividends.dividend_amount) = 0 
                THEN NULL 
                ELSE SUM(dividends.dividend_amount_base) / SUM(dividends.dividend_amount) 
                END AS dividends_rate")
            ->join('transactions', 'transactions.symbol', 'dividends.symbol')
            ->groupBy(['dividends.symbol', 'dividends.date', 'dividends.dividend_amount', 'dividends.dividend_amount_base'])
            ->orderBy('dividends.date', 'DESC')
            ->where('dividends.date', '>=', function ($query) {
                $query->selectRaw('min(transactions.date)')
                    ->from('transactions')
                    ->whereRaw("transactions.portfolio_id = '$this->portfolio_id'")
                    ->whereRaw("transactions.symbol = '$this->symbol'");
            })
            ->havingRaw("SUM(
                (CASE 
                    WHEN transaction_type = 'BUY'
                    AND transactions.symbol = dividends.symbol
                    AND transactions.portfolio_id = '$this->portfolio_id'
                    AND transactions.date <= dividends.date
                THEN transactions.quantity 
                ELSE 0 
                END)
                - 
                (CASE 
                    WHEN transaction_type = 'SELL'
                    AND transactions.symbol = dividends.symbol
                    AND transactions.portfolio_id = '$this->portfolio_id'
                    AND transactions.date <= dividends.date
                THEN transactions.quantity 
                ELSE 0 
                END)
            ) * dividends.dividend_amount_base > 0");
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

    /**
     * Related chats for holding
     *
     * @return void
     */
    public function chats()
    {
        return $this->morphMany(AiChat::class, 'chatable')->where('user_id', auth()->user()->id);
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
            ->selectRaw('COALESCE(((market_data.market_value - holdings.average_cost_basis) / NULLIF(holdings.average_cost_basis, 0)) * 100, 0) AS market_gain_percent');
    }

    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('holdings.portfolio_id', $portfolio);
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('holdings.symbol', $symbol);
    }

    public function scopeWithoutWishlists($query)
    {
        return $query->whereHas('portfolio', function ($query) {
            $query->where('portfolios.wishlist', 0);
        });
    }

    public function scopeMyHoldings($query, $userId = null)
    {
        return $query->whereHas('portfolio', function ($query) use ($userId) {
            $query->whereRelation('users', 'id', $userId ?? auth()->user()->id);
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
        ])->selectRaw("SUM(CASE WHEN transaction_type = 'BUY' THEN quantity ELSE 0 END) AS qty_purchases")
            ->selectRaw("SUM(CASE WHEN transaction_type = 'SELL' THEN quantity ELSE 0 END) AS qty_sales")
            ->selectRaw("ROUND(CAST(SUM(CASE WHEN transaction_type = 'BUY' THEN cost_basis_base ELSE 0 END) / 
                        SUM(CASE WHEN transaction_type = 'BUY' THEN cost_basis ELSE 0 END) AS numeric),
                        4) AS cost_basis_rate")
            ->selectRaw("ROUND(CAST(SUM(CASE WHEN transaction_type = 'SELL' THEN (sale_price_base - cost_basis_base) ELSE 0 END) / 
                        SUM(CASE WHEN transaction_type = 'SELL' THEN (sale_price - cost_basis) ELSE 0 END) AS numeric),
                        5) AS realized_gain_rate")
            ->selectRaw("SUM(CASE WHEN transaction_type = 'SELL' THEN (sale_price_base - cost_basis_base) * quantity ELSE 0 END) AS realized_gain_dollars")
            ->selectRaw("SUM(CASE WHEN transaction_type = 'BUY' THEN (quantity * cost_basis_base) ELSE 0 END) AS total_cost_basis")
            ->first();

        $total_quantity = round($query->qty_purchases - $query->qty_sales, 4);

        $average_cost_basis = (
            $query->qty_purchases > 0
            && $total_quantity > 0
        ) ? $query->total_cost_basis / $query->qty_purchases
        : 0;

        // update holding
        $this->fill([
            'quantity' => $total_quantity,
            'average_cost_basis' => $average_cost_basis,
            'total_cost_basis' => $total_quantity * $average_cost_basis,
            'cost_basis_rate' => $query->cost_basis_rate,
            'realized_gain_dollars' => $query->realized_gain_dollars,
            'realized_gain_rate' => $query->realized_gain_rate,
            'dividends_earned' => $this->dividends->sum('total_received'),
            'dividends_rate' => $this->dividends->average('dividends_rate') ?? 1,
        ]);

        $this->save();
    }

    public function qtyOwned(?\Illuminate\Support\Carbon $date = null)
    {
        if ($date == null) {
            $date = now();
        }

        $transactions = $this->transactions->where('date', '<=', $date);

        $purchases = $transactions->where('transaction_type', 'BUY')->sum('quantity');

        $sales = $transactions->where('transaction_type', 'SELL')->sum('quantity');

        return $purchases - $sales;
    }

    public function dailyPerformance(
        ?\Illuminate\Support\Carbon $start_date = null,
        ?\Illuminate\Support\Carbon $end_date = null,
    ) {
        if ($start_date == null) {
            $start_date = now();
        }
        if ($end_date == null) {
            $end_date = now();
        }

        // MySQL default interval
        $date_interval = 'DATE_ADD(date, INTERVAL 1 DAY)';
        $castNumberType = 'decimal';

        // Use SQLite interval grammar
        if (config('database.default') === 'sqlite') {

            $date_interval = "date(date, '+1 day')";
        }

        // Default CTE time series query (for MySQL and SQLite)
        $timeSeriesQuery = DB::table(DB::raw("(
            WITH RECURSIVE date_series AS (
                SELECT '{$start_date->format('Y-m-d')}' AS date
                UNION ALL
                SELECT $date_interval
                FROM date_series
                WHERE date < '{$end_date->format('Y-m-d')}'
            )
            SELECT date_series.date
            FROM date_series
        ) as date_series"));

        // PGSql time series query
        if (config('database.default') === 'pgsql') {

            $timeSeriesQuery = DB::table(DB::raw("
                generate_series(
                    date '{$start_date->format('Y-m-d')}', 
                    date '{$end_date->format('Y-m-d')}', 
                    interval '1 day'
                ) as date_series"));

            $castNumberType = 'numeric';
        }

        // Set MySQL-like query CTE max iterations
        if (config('database.default') === 'mysql') {

            // MySQL default
            $max_recursion_var_name = 'cte_max_recursion_depth';

            // Determine if running MySQL or MariaDB
            $versionString = Arr::get(
                DB::select('SELECT VERSION() as version;'),
                '0', new \stdClass
            )->version;
            if (stripos($versionString, 'MariaDB') !== false) {
                $max_recursion_var_name = 'max_recursive_iterations'; // Must be MariaDB
            }

            DB::statement("SET $max_recursion_var_name=1000000;");
        }

        // Extracted query for counting QTY owned
        $quantityQuery = "ROUND(CAST(COALESCE(
            SUM(CASE WHEN transactions.transaction_type = 'BUY' THEN transactions.quantity ELSE 0 END) 
            - SUM(CASE WHEN transactions.transaction_type = 'SELL' THEN transactions.quantity ELSE 0 END),
            0
        ) AS {$castNumberType}), 3)";

        return $timeSeriesQuery
            ->select([
                'date_series.date',
                DB::raw("
                    {$quantityQuery} AS owned
                "),
                DB::raw("
                    CASE
                        WHEN ({$quantityQuery}) = 0 THEN 0
                        ELSE SUM(CASE
                            WHEN transactions.transaction_type = 'BUY' THEN transactions.quantity * transactions.cost_basis
                            ELSE 0
                        END)
                    END AS cost_basis
                "),
                DB::raw("COALESCE(SUM(CASE WHEN transaction_type = 'SELL' THEN ((sale_price - cost_basis) * quantity) ELSE 0 END), 0) AS realized_gains"),
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

    public function getFormattedTransactions()
    {
        $formattedTransactions = '';
        foreach ($this->transactions->sortByDesc('date') as $transaction) {
            $formattedTransactions .= ' * '.$transaction->date->format('Y-m-d')
                                    .' '.$transaction->transaction_type
                                    .' '.$transaction->quantity
                                    .' @ '.$transaction->cost_basis
                                    ." each \n\n";
        }

        return $formattedTransactions;
    }
}
