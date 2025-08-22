<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\MarketData\MarketDataInterface;
use App\Notifications\InvitedOnboardingNotification;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Portfolio extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'title',
        'notes',
        'wishlist',
    ];

    public static ?string $owner_id = null;

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($portfolio) {

            self::ensurePortfolioHasOwner($portfolio);
        });
    }

    protected $hidden = [];

    protected $casts = [
        'wishlist' => 'boolean',
    ];

    protected $with = ['users', 'transactions'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['owner', 'full_access', 'invite_accepted_at']);
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

    /**
     * Related chats for portfolio
     *
     * @return void
     */
    public function chats()
    {
        return $this->morphMany(AiChat::class, 'chatable')->where('user_id', auth()->user()->id);
    }

    public function scopeMyPortfolios()
    {
        return $this->whereHas('users', function ($query) {
            $query->where('user_id', auth()->user()->id);
        });
    }

    public function scopeFullAccess($query, $user_id = null)
    {
        return $query->whereHas('users', function ($query) use ($user_id) {
            $query->where('user_id', $user_id ?? auth()->user()->id)
                ->where(function ($query) {
                    $query->where('full_access', true)
                        ->orWhere('owner', true);
                });
        });
    }

    public function scopeWithoutWishlists()
    {
        return $this->where(['wishlist' => false]);
    }

    public function setOwnerIdAttribute($value)
    {
        // enable queued jobs to create portfolios with owners
        if (! auth()->user()?->id && ! $this->owner_id) {
            static::$owner_id = $value;
        }
    }

    public function getOwnerIdAttribute()
    {
        return $this->owner?->id;
    }

    public function getOwnerAttribute()
    {
        if (! $this->relationLoaded('user')) {

            $this->load('users');
        }

        return $this->users->where('pivot.owner', true)->first();
    }

    public static function ensurePortfolioHasOwner(self $portfolio)
    {
        // make sure we don't remove owner access
        if (! $portfolio->owner_id) {
            $owner[static::$owner_id ?? auth()->user()->id] = ['owner' => true];

            // save
            $portfolio->users()->sync($owner);
            static::$owner_id = null;
        }
    }

    /**
     * Writes daily change history for a portfolio to the database
     */
    public function syncDailyChanges(): void
    {
        $holdings = $this->holdings()
            ->join('transactions', function ($join) {
                $join->on('transactions.symbol', '=', 'holdings.symbol')
                    ->where('transactions.portfolio_id', '=', $this->id);
            })
            ->select('holdings.symbol', 'holdings.portfolio_id', DB::raw('min(transactions.date) as first_transaction_date')) // get first transaction date
            ->groupBy(['holdings.symbol', 'holdings.portfolio_id'])
            ->get();

        $total_performance = [];

        // get unique currencies for holdings
        $currency_rates = [];
        foreach ($holdings->groupBy('market_data.currency')->keys() as $currency) {
            $currency_rates[$currency] = CurrencyRate::timeSeriesRates($currency, $holdings->min('first_transaction_date'), now());
        }

        $holdings->each(function ($holding) use (&$total_performance, $currency_rates) {

            $period = CarbonPeriod::create(
                $holding->first_transaction_date,
                now()->isBefore(Carbon::parse(config('investbrain.daily_change_time_of_day')))
                    ? now()->subDay()
                    : now()
            );

            $daily_performance = $holding->dailyPerformance($holding->first_transaction_date, now());
            $all_history = app(MarketDataInterface::class)->history($holding->symbol, $holding->first_transaction_date, now());

            $holding_performance = [];

            foreach ($period as $date) {
                $date = $date->toDateString();

                $close = $this->getMostRecentCloseData($all_history, $date);

                $total_market_value = $daily_performance->get($date)->owned * $close;

                if (Carbon::parse($date)->isWeekday()) {

                    $holding_performance[$date] = [
                        'date' => $date,
                        'portfolio_id' => $this->id,
                        'total_market_value' => $total_market_value * (1 / Arr::get($currency_rates[$holding->market_data->currency], $date, 1)),
                    ];
                }
            }

            foreach ($holding_performance as $date => $performance) {
                if (Arr::get($total_performance, $date) == null) {

                    $total_performance[$date] = $performance;

                } else {

                    $total_performance[$date]['total_market_value'] += $performance['total_market_value'];
                }
            }
        });

        if (! empty($total_performance)) {
            DB::transaction(function () use ($total_performance) {

                // delete old history
                $firstDate = array_keys($total_performance)[0];
                $this->daily_change()->where('date', '<', $firstDate)->delete();

                // upsert new history
                $this->daily_change()->upsert(
                    $total_performance,
                    ['date', 'portfolio_id'],
                    [
                        'total_market_value',
                    ]
                );
            });
        }

        cache()->forget('graph-YTD-'.$this->id);
        cache()->forget('graph-YTD-'.request()->user()->id);
    }

    protected function getMostRecentCloseData($history, $date, $i = 0, $max_attempts = 5)
    {
        $close = Arr::get($history, "$date.close", 0);

        if (! $close && $i < $max_attempts) {

            $i++;

            $date = Carbon::parse($date)->subDay()->toDateString();

            return $this->getMostRecentCloseData($history, $date, $i);
        }

        return $close;
    }

    public function getFormattedHoldings()
    {
        $formattedHoldings = '';
        foreach ($this->holdings as $holding) {
            $formattedHoldings .= ' * Holding of '.$holding->market_data->name.' ('.$holding->symbol.')'
                                    .'; with '.($holding->quantity > 0 ? $holding->quantity : 'ZERO').' shares'
                                    .'; avg cost basis '.$holding->average_cost_basis
                                    .'; curr market value '.$holding->market_data->market_value
                                    .'; unrealized gains '.$holding->market_gain_dollars
                                    .'; realized gains '.$holding->realized_gain_dollars
                                    .'; dividends earned '.$holding->dividends_earned
                                    ."\n\n";
        }

        return $formattedHoldings;
    }

    /**
     * Share a portfolio with a user
     */
    public function share(string $email, bool $fullAccess = false): void
    {
        $user = User::firstOrCreate([
            'email' => $email,
        ], [
            'name' => Str::title(Str::before($email, '@')),
        ]);

        $permissions[$user->id] = [
            'full_access' => $fullAccess,
        ];

        $sync = $this->users()->syncWithoutDetaching($permissions);

        if (! empty($sync['attached'])) {

            foreach ($sync['attached'] as $newUserId) {
                User::find($newUserId)->notify(new InvitedOnboardingNotification($this, auth()->user()));
            }
        }
    }

    /**
     * Un-share a portfolio
     */
    public function unShare(string $userId): void
    {
        $this->users()->detach($userId);
    }
}
