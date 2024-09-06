<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Interfaces\MarketData\MarketDataInterface;
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
        'last_date' => 'datetime',
    ];

    public function marketData() {
        return $this->belongsTo(MarketData::class, 'symbol', 'symbol');
    }

    public function holdings() {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function transactions() {
        return $this->hasMany(Transaction::class, 'symbol', 'symbol');
    }

    /**
     * Grab new dividend data
     *
     * @param string $symbol
     * @return void
     */
    public static function refreshDividendData(string $symbol) 
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
            self::syncHoldings($dividend_data);

            // sync last dividend amount to market data table
            $market_data = MarketData::symbol($symbol)->first();
            $market_data->last_dividend_amount = $dividend_data->sortByDesc('date')->first()['amount'];
            $market_data->save();
        }

        return $dividend_data;
    }

    public static function syncHoldings($dividend_data): void
    {
        $symbol = $dividend_data->last()->symbol;

        // group by holdings
        $dividends = self::where([
                        'dividends.symbol' => $dividend_data->last()->symbol,
                    ])
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
        Holding::where(['symbol' => $symbol])
                ->get()
                ->each(function ($holding) use ($dividends) {
                    $holding->update([
                        'dividends_earned' => $dividends->where('portfolio_id', $holding->portfolio_id)
                                                        ->sum('dividends_received')
                    ]);
                });
    }
}
