<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AverageCost;
use App\Traits\HasMarketData;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    ];

    protected $casts = [
        'reinvest_dividends' => 'boolean',
        'splits_synced_at' => 'datetime',
        'first_transaction_date' => 'datetime',
        'quantity' => 'float',
        'average_cost_basis' => 'float',
        'total_cost_basis' => 'float',
        'realized_gain_dollars' => 'float',
        'dividends_earned' => 'float',
        'total_market_gain_dollars' => 'float',
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
     * Dividends paid while this holding owned shares, with the shares owned and amount received
     */
    public function dividends(): HasMany
    {
        return $this->hasMany(Dividend::class, 'symbol', 'symbol')
            ->withSharesOwned($this->portfolio_id)
            ->orderBy('dividends.date', 'DESC');
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

    public function chatWithConversation(): MorphOne
    {
        return $this->morphOne(ChatWithConversation::class, 'chatable')
            ->where('user_id', auth()->id());
    }

    public function scopeWithMarketData($query)
    {
        return $query->withAggregate('market_data', 'name')
            ->withAggregate('market_data', 'market_value')
            ->withAggregate('market_data', 'market_value_base')
            ->withAggregate('market_data', 'fifty_two_week_low')
            ->withAggregate('market_data', 'fifty_two_week_high')
            ->withAggregate('market_data', 'updated_at')
            ->join('market_data', 'holdings.symbol', 'market_data.symbol');
    }

    /**
     * Calculate performance for holding in its local currency
     */
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

    /**
     * Scope which returns collection of performance metrics for holdings
     *
     * @param  string  $currency  Allows casting to specified currency
     */
    public function scopeGetPortfolioMetrics($query, $currency = null): Collection
    {
        $result = $query->withPortfolioMetrics($currency)->get();

        return collect([
            'total_cost_basis' => $result->sum('total_cost_basis'),
            'total_market_value' => $result->sum('total_market_value'),
            'total_market_gain_dollars' => $result->sum('total_market_gain_dollars'),
            'realized_gain_dollars' => $result->sum('realized_gain_dollars'),
            'total_dividends_earned' => $result->sum('total_dividends_earned'),
        ]);
    }

    /**
     * Scope to collect performance metrics for holdings
     *
     * Cost basis, realized gains and dividends are converted at the rate on the day they happened,
     * market value at today's rate.
     *
     * @param  string  $currency  Allows casting to specified currency
     */
    public function scopeWithPortfolioMetrics($query, $currency = null): mixed
    {
        $currency = $currency ?? auth()->user()->getCurrency();

        // only aggregate the portfolios being asked about, not every portfolio in the database
        $portfolioIds = $query->clone()->select('holdings.portfolio_id');

        // the last day each holding was left with no shares, after which its current shares were bought
        $day = Transaction::dateSql('transactions.date');
        $sharesOwnedByDay = DB::table('transactions')
            ->select(['transactions.symbol', 'transactions.portfolio_id'])
            ->selectRaw("{$day} AS day")
            ->selectRaw('SUM(SUM('.Transaction::signedQuantitySql().")) OVER (PARTITION BY transactions.symbol, transactions.portfolio_id ORDER BY {$day}) AS owned")
            ->whereIn('transactions.portfolio_id', $portfolioIds)
            ->groupBy(['transactions.symbol', 'transactions.portfolio_id', DB::raw($day)]);

        $soldOut = DB::query()
            ->fromSub($sharesOwnedByDay, 'shares_owned_by_day')
            ->select(['symbol', 'portfolio_id'])
            ->selectRaw('MAX(day) AS sold_out_on')
            ->whereRaw('owned < 0.00005')
            ->groupBy(['symbol', 'portfolio_id']);

        $currentPurchase = "transactions.transaction_type = 'BUY' AND (sold_out.sold_out_on IS NULL OR {$day} > sold_out.sold_out_on)";

        $transactions = DB::table('transactions')
            ->leftJoin('currency_rates as cr', CurrencyRate::joinOnDate('transactions.date', $currency))
            ->leftJoinSub($soldOut, 'sold_out', function ($join) {
                $join->on('sold_out.symbol', '=', 'transactions.symbol')
                    ->on('sold_out.portfolio_id', '=', 'transactions.portfolio_id');
            })
            ->select(['transactions.symbol', 'transactions.portfolio_id'])
            ->selectRaw("
                SUM(CASE WHEN transactions.transaction_type = 'SELL'
                    THEN (transactions.sale_price_base - transactions.cost_basis_base) * transactions.quantity * COALESCE(cr.rate, 1)
                END) AS realized_gain_dollars
            ")
            ->selectRaw("SUM(CASE WHEN {$currentPurchase} THEN transactions.cost_basis_base * transactions.quantity * COALESCE(cr.rate, 1) END) AS current_purchases_cost")
            ->selectRaw("SUM(CASE WHEN {$currentPurchase} THEN transactions.cost_basis * transactions.quantity END) AS current_purchases_cost_native")
            ->whereIn('transactions.portfolio_id', $portfolioIds)
            ->groupBy(['transactions.symbol', 'transactions.portfolio_id']);

        $dividends = DB::table('dividends')
            ->join('transactions', Dividend::joinTransactionsUpToDate())
            ->leftJoin('currency_rates as cr', CurrencyRate::joinOnDate('dividends.date', $currency))
            ->select(['dividends.symbol', 'transactions.portfolio_id'])
            ->selectRaw('SUM(('.Transaction::signedQuantitySql().') * dividends.dividend_amount_base * COALESCE(cr.rate, 1)) AS total_dividends_earned')
            ->whereIn('transactions.portfolio_id', $portfolioIds)
            ->groupBy(['dividends.symbol', 'transactions.portfolio_id']);

        $marketValue = 'holdings.quantity * market_data.market_value_base * COALESCE(cr.rate, 1)';
        // the holding's cost basis is in the market data currency, so convert it at the rates the current shares were bought at
        $costBasis = 'holdings.total_cost_basis * COALESCE(holding_transactions.current_purchases_cost / NULLIF(holding_transactions.current_purchases_cost_native, 0), 1)';

        return $query
            ->select([
                'holdings.symbol',
                'holdings.portfolio_id',
                'holding_transactions.realized_gain_dollars',
                'holding_dividends.total_dividends_earned',
            ])
            ->selectRaw("{$marketValue} AS total_market_value")
            ->selectRaw("{$costBasis} AS total_cost_basis")
            ->selectRaw("{$marketValue} - {$costBasis} AS total_market_gain_dollars")
            ->leftJoin('market_data', 'market_data.symbol', '=', 'holdings.symbol')
            ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                $join->whereDate('cr.date', '=', now()->toDateString())
                    ->where('cr.currency', '=', $currency);
            })
            ->leftJoinSub($transactions, 'holding_transactions', function ($join) {
                $join->on('holding_transactions.symbol', '=', 'holdings.symbol')
                    ->on('holding_transactions.portfolio_id', '=', 'holdings.portfolio_id');
            })
            ->leftJoinSub($dividends, 'holding_dividends', function ($join) {
                $join->on('holding_dividends.symbol', '=', 'holdings.symbol')
                    ->on('holding_dividends.portfolio_id', '=', 'holdings.portfolio_id');
            });
    }

    /**
     * Recalculates the holding from its transactions and dividends
     *
     * Walks the transactions oldest first to give each sale the average cost of the shares owned when
     * it happened, so selling every share starts the average over for shares bought later.
     */
    public function syncTransactionsAndDividends()
    {
        $transactions = Transaction::where([
            'portfolio_id' => $this->portfolio_id,
            'transactions.symbol' => $this->symbol,
        ])->inTradingOrder()
            ->get(['id', 'transaction_type', 'quantity', 'cost_basis', 'cost_basis_base', 'sale_price']);

        // delete holding if no transactions
        if (empty($transactions->sum('quantity'))) {

            $this->delete();

            return;
        }

        $averageCost = new AverageCost;
        $realized_gain_dollars = 0;

        foreach ($transactions as $transaction) {

            $sold = $averageCost->apply($transaction);

            if (is_null($sold)) {
                continue;
            }

            $realized_gain_dollars += ($transaction->sale_price - $sold['cost_basis']) * $transaction->quantity;

            // quietly, as saving a transaction is what syncs the holding
            if (
                abs($sold['cost_basis'] - $transaction->cost_basis) > 0.000001
                || abs($sold['cost_basis_base'] - $transaction->cost_basis_base) > 0.000001
            ) {
                Transaction::whereKey($transaction->id)->toBase()->update($sold);
            }
        }

        // update holding
        $this->fill([
            'quantity' => round($averageCost->quantity, 4),
            'average_cost_basis' => $averageCost->averageCostBasis(),
            'total_cost_basis' => $averageCost->totalCost,
            'realized_gain_dollars' => $realized_gain_dollars,
            'dividends_earned' => $this->dividends->sum('total_received'),
        ]);

        $this->save();
    }

    /**
     * Shares owned at the end of a day, today by default
     */
    public function qtyOwned(?Carbon $date = null): float
    {
        return Transaction::where([
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
        ])->whereDate('date', '<=', ($date ?? now())->toDateString())
            ->sharesOwned();
    }

    /**
     * Shares owned, cost basis and realized gains at the end of each day in the period
     *
     * @return Collection<string, object{date: string, owned: float, cost_basis: float, realized_gains: float}>
     */
    public function dailyPerformance(
        ?Carbon $start_date = null,
        ?Carbon $end_date = null,
    ): Collection {
        $start_date = ($start_date ?? now())->toDateString();
        $end_date = ($end_date ?? now())->toDateString();

        $transactions = Transaction::where([
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
        ])->whereDate('date', '<=', $end_date)
            ->inTradingOrder()
            ->get(['date', 'transaction_type', 'quantity', 'cost_basis_base', 'sale_price_base'])
            ->values();

        $averageCost = new AverageCost;
        $realized_gains = 0;
        $next = 0;
        $performance = collect();

        foreach (CarbonPeriod::create($start_date, $end_date) as $date) {
            $date = $date->toDateString();

            // apply the transactions made up to and including this day
            while ($next < $transactions->count() && $transactions[$next]->date->toDateString() <= $date) {
                $transaction = $transactions[$next++];

                $sold = $averageCost->apply($transaction);

                if (! is_null($sold)) {
                    $realized_gains += ($transaction->sale_price_base - $sold['cost_basis_base']) * $transaction->quantity;
                }
            }

            $performance->put($date, (object) [
                'date' => $date,
                'owned' => round($averageCost->quantity, 3),
                'cost_basis' => $averageCost->totalCostBase,
                'realized_gains' => $realized_gains,
            ]);
        }

        return $performance;
    }

    public function getFormattedTransactions()
    {
        $formattedTransactions = '';
        foreach ($this->transactions->sortByDesc('date') as $transaction) {
            $formattedTransactions .= ' * '.$transaction->date->toDateString()
                                    .' '.$transaction->transaction_type
                                    .' '.$transaction->quantity
                                    .' @ '.$transaction->cost_basis
                                    ." each \n\n";
        }

        return $formattedTransactions;
    }
}
