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

        $transactionTotals = DB::table('transactions')
            ->select(['transactions.portfolio_id', 'transactions.date'])
            ->selectRaw("
                SUM(
                    (CASE WHEN transactions.transaction_type = 'BUY' THEN 1 ELSE -1 END) 
                    * transactions.quantity 
                    * transactions.cost_basis_base 
                    * COALESCE(cr.rate, 1)
                ) AS daily_cost_basis
            ")
            ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                $join
                    ->on(DB::raw('DATE(cr.date)'), '=', DB::raw('DATE(transactions.date)'))
                    ->where('cr.currency', $currency);
            })
            ->groupBy('transactions.portfolio_id', 'transactions.date');

        $cumulativeCostBasis = DB::table(DB::raw("({$transactionTotals->toSql()}) AS transaction_totals"))
            ->mergeBindings($transactionTotals)
            ->select(['portfolio_id', 'date'])
            ->selectRaw('SUM(daily_cost_basis) AS cumulative_cost_basis')
            ->groupBy('portfolio_id', 'date');

        return $query
            ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                $join
                    ->on(DB::raw('DATE(cr.date)'), '=', DB::raw('DATE(daily_change.date)'))
                    ->where('cr.currency', $currency);
            })
            ->leftJoinSub($cumulativeCostBasis, 'ccb', function ($join) {
                $join
                    ->on('ccb.portfolio_id', '=', 'daily_change.portfolio_id')
                    ->whereRaw('ccb.date <= daily_change.date');
            })
            ->select(['daily_change.portfolio_id', 'daily_change.date'])
            ->selectRaw('daily_change.total_market_value * COALESCE(cr.rate, 1) AS total_market_value')
            ->selectRaw('SUM(COALESCE(ccb.cumulative_cost_basis, 0)) AS total_cost_basis')
            ->groupBy(['daily_change.date', 'daily_change.portfolio_id', 'cr.rate'])
            ->orderBy('daily_change.date');
    }

    public function scopeGetDailyPerformance($query)
    {
        return $query->get()
            ->groupBy('date')
            ->map(function ($group) {

                $total_market_value = $group->sum('total_market_value');
                $total_cost_basis = $group->sum('total_cost_basis');
                $total_market_gain = $total_market_value - $total_cost_basis;

                return (object) [
                    'date' => $group->first()->date->toDateString(),
                    'total_market_value' => $total_market_value,
                    'total_cost_basis' => $total_cost_basis,
                    'total_gain' => $total_market_gain,
                    'realized_gain_dollars' => $group->sum('realized_gain_dollars'),
                    // 'total_dividends_earned' => $group->sum('total_dividends_earned'),
                ];
            })
            ->values();
    }

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
}
