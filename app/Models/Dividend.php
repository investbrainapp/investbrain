<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dividend extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'symbol',
        'date',
        'dividend_amount',
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'first_date' => 'datetime',
        'last_date' => 'datetime',
    ];

    /**
     * Syncs all holdings of symbol with dividend data
     *
     * @param array|self $model
     * @return void
     */
    public static function syncHoldings(mixed $model) 
    {
        // check if we got an array, if yes then lets create a dummy model
        if (is_array($model)) {
            $model = (new self)->fill($model);
        }

        // pull dividend data joined with holdings/transactions
        $dividends = self::where(['dividends.symbol' => $model->symbol])
                ->select(['holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount'])
                ->selectRaw('@purchased:=(SELECT coalesce(SUM(quantity),0) FROM transactions WHERE transactions.transaction_type = "BUY" AND transactions.symbol = dividends.symbol AND date(transactions.date) <= date(dividends.date) AND holdings.portfolio_id = transactions.portfolio_id ) AS `purchased`')
                ->selectRaw('@sold:=(SELECT coalesce(SUM(quantity),0) FROM transactions WHERE transactions.transaction_type = "SELL" AND transactions.symbol = dividends.symbol AND date(transactions.date) <= date(dividends.date)  AND holdings.portfolio_id = transactions.portfolio_id ) AS `sold`')
                ->selectRaw('@owned:=(@purchased - @sold) AS `owned`')
                ->selectRaw('@dividends_received:=(@owned * dividends.dividend_amount) AS `dividends_received`')
                ->join('transactions', 'transactions.symbol', 'dividends.symbol')
                ->join('holdings', 'transactions.portfolio_id', 'holdings.portfolio_id')
                ->groupBy(['holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount'])
                ->get();

        // iterate through holdings and update 
        Holding::where(['symbol' => $model->symbol])
            ->get()
            ->each(function ($holding) use ($dividends) {
                $holding->update([
                    'dividends_earned' => $dividends->where('portfolio_id', $holding->portfolio_id)->sum('dividends_received')
                ]);
            });
    }

    public function marketData() {
        return $this->belongsTo(MarketData::class, 'symbol', 'symbol');
    }

    public function holdings() {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function transactions() {
        return $this->hasMany(Transaction::class, 'symbol', 'symbol');
    }
}
