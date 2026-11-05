<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DailyChange extends Model
{
    use HasCompositePrimaryKey, HasFactory;

    public $timestamps = false;

    protected $primaryKey = ['date', 'portfolio_id'];

    protected $table = 'daily_change';

    protected $fillable = [
        'portfolio_id',
        'date',
        'total_market_value',
        'notes',
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'total_market_value' => 'float',
        'total_cost_basis' => 'float',
        'total_market_gain' => 'float',
        'realized_gain_dollars' => 'float',
        'total_dividends_earned' => 'float',
    ];

    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('daily_change.portfolio_id', $portfolio);
    }

    public function scopeMyDailyChanges($query)
    {
        return $query->whereHas('portfolio', function ($query) {
            $query->whereHas('users', function ($query) {
                return $query->where('id', auth()->id());
            });
        });
    }

    public function scopeWithoutWishlists($query)
    {
        return $query->whereHas('portfolio', function ($query) {
            $query->where('portfolios.wishlist', 0);
        });
    }

    /**
     * Adds the cost basis, realized gains and dividends earned up to each day, in the user's display currency
     */
    public function scopeWithDailyPerformance($query)
    {
        $currency = auth()->user()?->getCurrency() ?? config('investbrain.base_currency');

        // only aggregate the portfolios being asked about, not every portfolio in the database
        $portfolioIds = $query->clone()->select('daily_change.portfolio_id');

        $transactionChanges = DB::table('transactions')
            ->leftJoin('currency_rates as cr', CurrencyRate::joinOnDate('transactions.date', $currency))
            ->select('transactions.portfolio_id')
            ->selectRaw(Transaction::dateSql('transactions.date').' AS date')
            ->selectRaw('('.Transaction::signedQuantitySql().') * transactions.cost_basis_base * COALESCE(cr.rate, 1) AS cost_basis')
            ->selectRaw("
                CASE WHEN transactions.transaction_type = 'SELL'
                    THEN (transactions.sale_price_base - transactions.cost_basis_base) * transactions.quantity * COALESCE(cr.rate, 1)
                    ELSE 0
                END AS realized_gains
            ")
            ->selectRaw('0 AS dividends_earned')
            ->whereIn('transactions.portfolio_id', $portfolioIds);

        $dividendChanges = DB::table('dividends')
            ->join('transactions', Dividend::joinTransactionsUpToDate())
            ->leftJoin('currency_rates as cr', CurrencyRate::joinOnDate('dividends.date', $currency))
            ->select('transactions.portfolio_id')
            ->selectRaw(Transaction::dateSql('dividends.date').' AS date')
            ->selectRaw('0 AS cost_basis')
            ->selectRaw('0 AS realized_gains')
            ->selectRaw('('.Transaction::signedQuantitySql().') * dividends.dividend_amount_base * COALESCE(cr.rate, 1) AS dividends_earned')
            ->whereIn('transactions.portfolio_id', $portfolioIds);

        // running totals for each day a portfolio changed, which hold until the next change (next_date)
        $window = 'OVER (PARTITION BY portfolio_id ORDER BY date)';
        $runningTotals = DB::query()
            ->fromSub($transactionChanges->unionAll($dividendChanges), 'changes')
            ->select(['portfolio_id', 'date'])
            ->selectRaw("LEAD(date) {$window} AS next_date")
            ->selectRaw("SUM(SUM(cost_basis)) {$window} AS cost_basis")
            ->selectRaw("SUM(SUM(realized_gains)) {$window} AS realized_gains")
            ->selectRaw("SUM(SUM(dividends_earned)) {$window} AS dividends_earned")
            ->groupBy(['portfolio_id', 'date']);

        $marketValue = 'daily_change.total_market_value * COALESCE(cr.rate, 1)';

        return $query
            ->select(['daily_change.date', 'daily_change.portfolio_id'])
            ->selectRaw("{$marketValue} AS total_market_value")
            ->selectRaw('COALESCE(running_totals.cost_basis, 0) AS total_cost_basis')
            ->selectRaw("{$marketValue} - COALESCE(running_totals.cost_basis, 0) AS total_market_gain")
            ->selectRaw('COALESCE(running_totals.realized_gains, 0) AS realized_gain_dollars')
            ->selectRaw('COALESCE(running_totals.dividends_earned, 0) AS total_dividends_earned')
            ->leftJoin('currency_rates as cr', CurrencyRate::joinOnDate('daily_change.date', $currency))
            ->leftJoinSub($runningTotals, 'running_totals', function ($join) {
                $join->on('running_totals.portfolio_id', '=', 'daily_change.portfolio_id')
                    ->on('running_totals.date', '<=', 'daily_change.date')
                    ->where(fn ($join) => $join
                        ->whereColumn('running_totals.next_date', '>', 'daily_change.date')
                        ->orWhereNull('running_totals.next_date'));
            })
            ->orderBy('daily_change.date');
    }

    public function scopeWithMultipleDailyPerformance($query)
    {
        return DB::query()
            ->fromSub($query, 'daily_query')
            ->select('date')
            ->selectRaw('SUM(total_market_value) AS total_market_value')
            ->selectRaw('SUM(total_cost_basis) AS total_cost_basis')
            ->selectRaw('SUM(total_market_gain) AS total_market_gain')
            ->selectRaw('SUM(realized_gain_dollars) AS realized_gain_dollars')
            ->selectRaw('SUM(total_dividends_earned) AS total_dividends_earned')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
}
