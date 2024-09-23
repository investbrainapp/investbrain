<?php

namespace App\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Interfaces\MarketData\MarketDataInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Portfolio extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'title',
        'notes',
        'wishlist',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($model) {

            self::syncUsers($model);
        });
    }

    protected $hidden = [];

    protected $casts = [
        'wishlist' => 'boolean'
    ];

    protected $with = ['users', 'transactions'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('owner');
    }

    public function holdings()
    {
        return $this->hasMany(Holding::class, 'portfolio_id')
                    ->withMarketData()
                    ->withPerformance();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderBy('created_at', 'DESC');
    }

    public function daily_change()
    {
        return $this->hasMany(DailyChange::class);
    }

    public function scopeMyPortfolios() 
    {
        return $this->whereHas('users', function ($query) {
            $query->where('user_id', auth()->user()->id);
        });
    }

    public function scopeWithoutWishlists() 
    {
        return $this->where(['wishlist' => false]);
    }

    public function getOwnerIdAttribute()
    {
        return $this->users()->firstWhere('owner', 1)?->id;
    }

    public static function syncUsers(self $model) 
    {
        // make sure we don't remove owner access
        $user_id[$model->owner_id ?? auth()->user()->id] = ['owner' => true];

        // // add other users
        // foreach(request()->users ?? [] as $id) {
        //     $user_id[$id] = ['owner' => false];
        // };

        // save
        $model->users()->sync($user_id);
    }

    public function syncDailyChanges(): void
    {
        $holdings = $this->holdings()
                ->join('transactions', function($join) {
                    $join->on('transactions.symbol', '=', 'holdings.symbol')
                         ->where('transactions.portfolio_id', '=', $this->id); 
                })
                ->select('holdings.symbol', 'holdings.portfolio_id', DB::raw('min(transactions.date) as first_transaction_date')) // get first transaction date
                ->groupBy(['holdings.symbol', 'holdings.portfolio_id']) 
                ->get();

        $dividends = Dividend::whereIn('symbol', $holdings->pluck('symbol'))->get();
        
        $total_performance = [];

        $holdings->each(function($holding) use (&$total_performance, $dividends) {

            $holding->setRelation('dividends', $dividends->where('symbol', $holding->symbol));

            $daily_performance = $holding->dailyPerformance($holding->first_transaction_date, now());
            $dividends = $holding->dividends->keyBy(function ($dividend, $key) {
                return $dividend['date']->format('Y-m-d');
            });
            $all_history = app(MarketDataInterface::class)->history($holding->symbol, $holding->first_transaction_date, now());

            $dividends_earned = 0;
            $daily = [];

            $daily_performance->each(function ($performance, $date) use ($all_history, $dividends, &$daily, &$dividends_earned) {

                $close = $this->getMostRecentCloseData($all_history, $date);
                
                $total_market_value = $performance->owned * $close;
                $dividends_earned += $performance->owned * ($dividends->get($date)?->dividend_amount ?? 0);

                $daily[$date] = [
                    'date' => $date,
                    'portfolio_id' => $this->id,
                    'total_market_value' => $total_market_value, 
                    'total_cost_basis' => $performance->cost_basis,
                    'total_gain' => $total_market_value - $performance->cost_basis,
                    'realized_gains' => $performance->realized_gains,
                    'total_dividends_earned' => $dividends_earned
                ];
            });

            foreach ($daily as $date => $performance) {
                if (Arr::get($total_performance, $date) == null) {
                    
                    $total_performance[$date] = $performance;

                } else {

                    $total_performance[$date]['total_market_value'] += $performance['total_market_value'];
                    $total_performance[$date]['total_cost_basis'] += $performance['total_cost_basis'];
                    $total_performance[$date]['total_gain'] += $performance['total_gain'];
                    $total_performance[$date]['realized_gains'] += $performance['realized_gains'];
                    $total_performance[$date]['total_dividends_earned'] += $performance['total_dividends_earned'];
                }
            }
        });

        if (!empty($total_performance)) {
            DB::transaction(function () use ($total_performance) {
                $this->daily_change()->delete();

                DailyChange::insert($total_performance);
            });
        }
    }

    protected function getMostRecentCloseData($history, $date, $i = 0, $max_attempts = 5)
    {
        $close = Arr::get($history, "$date.close", 0);

        if (!$close && $i < $max_attempts) {

            $i++;
    
            $date = Carbon::parse($date)->subDay()->format('Y-m-d');

            return $this->getMostRecentCloseData($history, $date, $i);
        }

        return $close;
    }

}
