<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\MarketData\MarketDataInterface;
use App\Traits\HasMarketData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Dividend extends Model
{
    use HasFactory;
    use HasMarketData;
    use HasUuids;

    protected $fillable = [
        'symbol',
        'date',
        'dividend_amount',
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'last_dividend_update' => 'datetime',
    ];

    public function holdings(): HasMany
    {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'symbol', 'symbol');
    }

    public function scopeSymbol($query, $symbol)
    {
        return $query->where('dividends.symbol', $symbol);
    }

    /**
     * Grab new dividend data
     */
    public static function refreshDividendData(string $symbol): void
    {
        $dividends_meta = self::where(['symbol' => $symbol])
            ->selectRaw('COUNT(symbol) as total_dividends')
            ->selectRaw('MAX(created_at) as last_dividend_update')
            ->get()
            ->first();

        // assume we need to populate ALL dividend data
        $start_date = new Carbon('@0');
        $end_date = now();

        // nope, refresh forward looking only
        if ($dividends_meta->total_dividends) {

            $start_date = $dividends_meta->last_dividend_update->addHours(24);
        }

        // skip refresh if there's already recent data
        if ($start_date->greaterThan($end_date)) {

            return;
        }

        // get some data
        if ($dividend_data = collect() && $start_date && $end_date) {
            $dividend_data = app(MarketDataInterface::class)->dividends($symbol, $start_date, $end_date);
        }

        // ah, we found some dividends...
        if ($dividend_data->isNotEmpty()) {

            $market_data = MarketData::getMarketData($symbol);

            // get historic conversion rates
            $rate_to_base = Currency::timeSeriesRates($market_data->currency, config('investbrain.base_currency'), $start_date, $end_date);

            // create mass insert
            foreach ($dividend_data as $index => $dividend) {

                $dividend['dividend_amount_base'] = $dividend['dividend_amount'] * Arr::get($rate_to_base, Carbon::parse(Arr::get($dividend, 'date'))->format('Y-m-d'), 1);

                $dividend_data[$index] = [...$dividend, ...['id' => Str::uuid()->toString(), 'updated_at' => now(), 'created_at' => now()]];
            }

            // insert records
            (new self)->insert($dividend_data->toArray());

            // sync to holdings
            self::syncHoldings($symbol);

            // re-invest dividends
            self::reinvestDividends($dividend_data, $market_data);

            // sync last dividend amount to market data table
            $market_data->last_dividend_amount = $dividend_data->sortByDesc('date')->first()['dividend_amount'];
            $market_data->save();
        }
    }

    public static function syncHoldings(string $symbol): void
    {
        // group by holdings
        $subQuery = self::select([
            'holdings.portfolio_id',
            'dividends.date',
            'dividends.symbol',
            'dividends.dividend_amount',
        ])->selectRaw("
            (COALESCE(SUM(CASE WHEN transactions.transaction_type = 'BUY' 
                AND date(transactions.date) <= date(dividends.date) 
                THEN transactions.quantity ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN transactions.transaction_type = 'SELL' 
                AND date(transactions.date) <= date(dividends.date) 
                THEN transactions.quantity ELSE 0 END), 0))
            * dividends.dividend_amount
            AS total_received
        ")->join('transactions', 'transactions.symbol', '=', 'dividends.symbol')
            ->join('holdings', 'transactions.portfolio_id', '=', 'holdings.portfolio_id')
            ->where('dividends.symbol', $symbol)
            ->groupBy('holdings.portfolio_id', 'dividends.date', 'dividends.symbol', 'dividends.dividend_amount');

        $dividends = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
            ->mergeBindings($subQuery->getQuery())
            ->where('total_received', '>', 0)
            ->get();

        // iterate through holdings and update
        Holding::where(['symbol' => $symbol])
            ->get()
            ->each(function ($holding) use ($dividends) {
                $holding->update([
                    'dividends_earned' => $dividends->where('portfolio_id', $holding->portfolio_id)
                        ->sum('total_received'),
                ]);
            });
    }

    public static function reinvestDividends(iterable $dividend_data, MarketData $market_data): void
    {
        // re-invest dividends
        Holding::where([
            'symbol' => $market_data->symbol,
            'reinvest_dividends' => true,
        ])
            ->get()
            ->each(function ($holding) use ($dividend_data, $market_data) {

                foreach ($dividend_data as $dividend) {

                    Transaction::create([
                        'date' => $dividend['date'],
                        'portfolio_id' => $holding->portfolio_id,
                        'symbol' => $holding->symbol,
                        'transaction_type' => 'BUY',
                        'reinvested_dividend' => true,
                        'cost_basis' => 0,
                        'quantity' => ($dividend['dividend_amount'] * $holding->qtyOwned(Carbon::parse($dividend['date']))) / $market_data->market_value,
                    ]);
                }
            });
    }
}
