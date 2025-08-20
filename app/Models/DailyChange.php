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

        return $query
            ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                $join->on(DB::raw('DATE(cr.date)'), '=', DB::raw('DATE(daily_change.date)'))
                    ->where('cr.currency', '=', $currency);
            })
            ->select([
                'daily_change.date',
                'daily_change.portfolio_id',
                DB::raw('daily_change.total_market_value * COALESCE(cr.rate, 1) as total_market_value'),
                'total_cost_basis' => function ($query) use ($currency) {
                    $query->from('transactions')
                        ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                            $join->on(DB::raw('DATE(cr.date)'), '=', DB::raw('DATE(transactions.date)'))
                                ->where('cr.currency', '=', $currency);
                        })
                        ->selectRaw('SUM(
                    (CASE WHEN transactions.transaction_type = \'BUY\' THEN 1 ELSE -1 END)
                    * transactions.cost_basis_base * transactions.quantity * COALESCE(cr.rate, 1)
                )')
                        ->whereColumn('transactions.portfolio_id', 'daily_change.portfolio_id')
                        ->whereColumn('transactions.date', '<=', 'daily_change.date');
                },
                'realized_gain_dollars' => function ($query) use ($currency) {
                    $query->from('transactions')
                        ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                            $join->on(DB::raw('DATE(cr.date)'), '=', DB::raw('DATE(transactions.date)'))
                                ->where('cr.currency', '=', $currency);
                        })
                        ->selectRaw('SUM(
                    (CASE WHEN transactions.transaction_type = \'SELL\' 
                        THEN (transactions.sale_price_base - transactions.cost_basis_base) 
                        ELSE 0 END)
                    * transactions.quantity * COALESCE(cr.rate, 1)
                )')
                        ->whereColumn('transactions.portfolio_id', 'daily_change.portfolio_id')
                        ->whereColumn('transactions.date', '<=', 'daily_change.date');
                },
                // 'total_dividends_earned' => function ($query) use ($currency) {
                //     $query->from('holdings')
                //         ->join('dividends', 'dividends.symbol', '=', 'holdings.symbol')
                //         ->leftJoin('currency_rates as cr', function ($join) use ($currency) {
                //             $join->on(DB::raw('DATE(cr.date)'), '=', DB::raw('DATE(dividends.date)'))
                //                 ->where('cr.currency', '=', $currency);
                //         })
                //         ->join('transactions as tx', function ($join) {
                //             $join->on('tx.symbol', '=', 'holdings.symbol')
                //                 ->on('tx.portfolio_id', '=', 'holdings.portfolio_id')
                //                 ->whereColumn('tx.date', '<=', 'dividends.date');
                //         })
                //         ->selectRaw('SUM(
                //     ((CASE WHEN tx.transaction_type = \'BUY\' THEN tx.quantity ELSE 0 END)
                //     - (CASE WHEN tx.transaction_type = \'SELL\' THEN tx.quantity ELSE 0 END))
                //     * dividends.dividend_amount_base
                //     * COALESCE(cr.rate, 1)
                // )')
                //         ->whereColumn('holdings.portfolio_id', 'daily_change.portfolio_id')
                //         ->whereColumn('dividends.date', '<=', 'daily_change.date');
                // },
            ])
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
