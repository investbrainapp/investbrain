<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Interfaces\MarketData\MarketDataInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dividend extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'symbol',
        'date',
        'dividend_amount',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
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
        $dividends = self::where([
            'dividends.symbol' => $model->symbol,
        ])->select(['holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount'])
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

    /**
     * Grab new dividend data
     *
     * @param string $symbol
     * @return void
     */
    public static function getDividendData(string $symbol) 
    {
        $dividends_meta = self::where(['symbol' => $symbol])
            ->selectRaw('COUNT(symbol) as total_dividends')
            ->selectRaw('MAX(date) as last_date')
            ->get()
            ->first();

        // assume we need to populate ALL dividend data
        $start_date = new \DateTime('@0');
        $end_date = now();

        // nope, refresh forward looking only
        if ( $dividends_meta->total_dividends ) {

            $start_date = $dividends_meta->last_date->addHours(48);
            $end_date =  now();
        }

        // get some data
        if ($dividend_data = collect() && $start_date && $end_date) {
            $dividend_data = app(MarketDataInterface::class)->dividends($symbol, $start_date, $end_date);
        }

        // ah, we found some dividends...
        if ($dividend_data->isNotEmpty()) {
            // create mass insert
            foreach ($dividend_data as $index => $dividend){
                $dividend_data[$index] = [...$dividend, ...['updated_at' => now(), 'created_at' => now()]];
            }

            // insert records
            (new self)->insert($dividend_data->toArray());

            // sync to holdings
            self::syncHoldings($dividend_data->last());

            // sync most last dividend date in market data
            $market_data = MarketData::symbol($symbol)->first();
            $dividend_data_latest_date = $dividend_data->sortByDesc('date')->first()['date'];
            
            if ($market_data->dividend_date < $dividend_data_latest_date) {
                $market_data->update(['dividend_date' => $dividend_data_latest_date]); // why is this set to latest date?
            }
        }

        return $dividend_data;
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
