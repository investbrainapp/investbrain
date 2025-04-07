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
        'total_gain' => 'float',
        'realized_gain_dollars' => 'float',
        'total_dividends_earned' => 'float',
    ];

    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('daily_change.portfolio_id', $portfolio);
    }

    public function scopeMyDailyChanges()
    {
        return $this->whereHas('portfolio', function ($query) {
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

    public function scopeWithDailyPerformance($query)
    {
        $currency = auth()->user()?->getCurrency() ?? config('investbrain.base_currency');

        $dividendSub = DB::table('holdings')
            ->join('dividends', 'dividends.symbol', '=', 'holdings.symbol')
            ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                $join->on('cr.date', '=', 'dividends.date')
                    ->where('cr.currency', '=', $currency);
            })
            ->join('transactions as tx', function ($join) {
                $join->on('tx.symbol', '=', 'holdings.symbol')
                    ->on('tx.portfolio_id', '=', 'holdings.portfolio_id')
                    ->whereColumn('tx.date', '<=', 'dividends.date');
            })
            ->select(['holdings.portfolio_id', 'dividends.date'])
            ->selectRaw("
                ((CASE WHEN tx.transaction_type = 'BUY'
                    THEN tx.quantity ELSE 0 END)
                - (CASE WHEN tx.transaction_type = 'SELL'
                    THEN tx.quantity ELSE 0 END))
                * SUM(
                    dividends.dividend_amount_base
                    * COALESCE(cr.rate, 1)
                )
                AS total_dividends_earned")
            ->groupBy(['holdings.portfolio_id', 'dividends.date', 'tx.transaction_type', 'tx.quantity']);

        $totalCostBasisSub = DB::table('transactions as tx1')
            ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                $join->on('cr.date', '=', 'tx1.date')
                    ->where('cr.currency', '=', $currency);
            })
            ->select([
                'tx1.portfolio_id',
                'tx1.date',
                'tx1.symbol',
                'tx1.transaction_type',
                'tx1.quantity',
            ])
            ->selectRaw("(CASE
                WHEN tx1.transaction_type = 'BUY'
                    THEN COALESCE(cr.rate, 1)
                ELSE (
                    SELECT
                        SUM(COALESCE(cr2.rate, 1) * buy.cost_basis_base)
                        / SUM(buy.cost_basis_base)
                    FROM transactions as buy
                    LEFT JOIN currency_rates as cr2
                        ON cr2.date = buy.date
                        AND cr2.currency = '{$currency}'
                    WHERE buy.symbol = tx1.symbol
                        AND buy.portfolio_id = tx1.portfolio_id
                        AND buy.transaction_type = 'BUY'
                        AND buy.date <= tx1.date
                ) END)
                AS rate")
            ->selectRaw(
                "(CASE
                WHEN tx1.transaction_type = 'BUY'
                    THEN AVG(tx1.cost_basis_base)
                ELSE (
                    SELECT
                        AVG(-buy.cost_basis_base)
                    FROM transactions as buy
                    WHERE buy.symbol = tx1.symbol
                        AND buy.portfolio_id = tx1.portfolio_id
                        AND buy.transaction_type = 'BUY'
                        AND buy.date <= tx1.date
                ) END)
                AS cost_basis_base")
            ->selectRaw(
                "(CASE
                WHEN tx1.transaction_type = 'SELL'
                    THEN tx1.sale_price_base - tx1.cost_basis_base
                ELSE 0 END)
                * tx1.quantity
                * COALESCE(cr.rate, 1)
                AS realized_gain_dollars")
            ->groupBy([
                'tx1.portfolio_id',
                'tx1.date',
                'tx1.symbol',
                'tx1.transaction_type',
                'tx1.cost_basis_base',
                'tx1.quantity',
                'cr.rate',
                'tx1.sale_price_base',
            ]);

        return $query
            ->select(['daily_change.portfolio_id', 'daily_change.date'])
            ->leftJoinSub($totalCostBasisSub, 'cost_basis_display', function ($join) {
                $join->on('daily_change.date', '>=', 'cost_basis_display.date')
                    ->whereColumn('daily_change.portfolio_id', '=', 'cost_basis_display.portfolio_id');
            })
            ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                $join->on('cr.date', '=', 'daily_change.date')
                    ->where('cr.currency', '=', $currency);
            })
            ->selectRaw('
                SUM(
                    cost_basis_display.cost_basis_base
                    * cost_basis_display.quantity
                    * cost_basis_display.rate
                ) as total_cost_basis')
            ->selectRaw('(
                daily_change.total_market_value * COALESCE(cr.rate, 1)
                ) - SUM(
                    cost_basis_display.cost_basis_base
                    * cost_basis_display.quantity
                    * cost_basis_display.rate
                ) as total_gain')
            ->selectRaw('(
                daily_change.total_market_value * COALESCE(cr.rate, 1)
                ) as total_market_value')
            ->selectRaw('
                SUM(
                    cost_basis_display.realized_gain_dollars
                ) as realized_gain_dollars')
            ->selectSub(function ($query) use ($dividendSub) {
                $query->fromSub($dividendSub, 'd')
                    ->selectRaw('SUM(d.total_dividends_earned)')
                    ->whereColumn('d.date', '<=', 'daily_change.date')
                    ->whereColumn('d.portfolio_id', '=', 'daily_change.portfolio_id');
            }, 'total_dividends_earned')
            ->groupBy([
                'daily_change.date',
                'cr.rate',
                'daily_change.total_market_value',
                'daily_change.portfolio_id',
            ])
            ->orderBy('daily_change.date');
    }

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
}
