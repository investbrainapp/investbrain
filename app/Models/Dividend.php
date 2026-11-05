<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\CopyToBaseCurrency;
use App\Casts\BaseCurrency;
use App\Interfaces\MarketData\MarketDataInterface;
use App\Support\Sql;
use App\Traits\HasMarketData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Pipeline;
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
        'date' => 'date',
        'last_dividend_update' => 'date',
        'dividend_amount' => 'float',
        'dividend_amount_base' => BaseCurrency::class,
        'shares_owned' => 'float',
        'total_received' => 'float',
        'total_received_base' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($dividend) {

            $dividend = Pipeline::send($dividend)
                ->through([
                    CopyToBaseCurrency::class,
                ])
                ->then(fn (Dividend $dividend) => $dividend);
        });
    }

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
     * Adds the shares each portfolio owned on the dividend date, and the amount it received.
     * Dividends paid while a portfolio owned no shares are left out.
     */
    public function scopeWithSharesOwned(Builder $query, ?string $portfolioId = null): Builder
    {
        $sharesOwned = 'SUM('.Sql::signedQuantity().')';

        return $query
            ->select([
                'transactions.portfolio_id',
                'dividends.symbol',
                'dividends.date',
                'dividends.dividend_amount',
                'dividends.dividend_amount_base',
            ])
            ->selectRaw("{$sharesOwned} AS shares_owned")
            ->selectRaw("{$sharesOwned} * dividends.dividend_amount AS total_received")
            ->selectRaw("{$sharesOwned} * dividends.dividend_amount_base AS total_received_base")
            ->join('transactions', function (JoinClause $join) use ($portfolioId) {
                $join->on('transactions.symbol', '=', 'dividends.symbol')
                    ->on(DB::raw(Sql::date('transactions.date')), '<=', DB::raw(Sql::date('dividends.date')))
                    ->when($portfolioId, fn (JoinClause $join) => $join->where('transactions.portfolio_id', '=', $portfolioId));
            })
            ->groupBy([
                'transactions.portfolio_id',
                'dividends.symbol',
                'dividends.date',
                'dividends.dividend_amount',
                'dividends.dividend_amount_base',
            ])
            ->havingRaw("{$sharesOwned} > 0");
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

            $start_date = $dividends_meta->last_dividend_update;
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

            // the provider can return dividends we already stored, which must not be reinvested again
            $stored_dates = self::where('symbol', $symbol)
                ->whereDate('date', '>=', Carbon::parse($dividend_data->min('date')))
                ->pluck('date')
                ->map(fn ($date) => $date->toDateString());

            $new_dividends = $dividend_data->reject(
                fn ($dividend) => $stored_dates->contains(Carbon::parse(Arr::get($dividend, 'date'))->toDateString())
            );

            $dividend_data
                ->chunk(10)
                ->each(function ($chunk) use ($market_data) {

                    // get historic conversion rates
                    $rate_to_base = CurrencyRate::timeSeriesRates($market_data->currency, $chunk->min('date'), $chunk->max('date'));

                    // create mass insert
                    foreach ($chunk as $index => $dividend) {
                        $rate_to_base_date = 1 / Arr::get($rate_to_base, Carbon::parse(Arr::get($dividend, 'date'))->toDateString(), 1);

                        $dividend['dividend_amount_base'] = $dividend['dividend_amount'] * $rate_to_base_date;

                        $chunk[$index] = [...$dividend, ...['id' => Str::uuid()->toString(), 'updated_at' => now(), 'created_at' => now()]];
                    }

                    // insert records
                    (new self)->insertOrIgnore($chunk->toArray());
                });

            // sync to holdings
            self::syncHoldings($symbol);

            // re-invest dividends
            self::reinvestDividends($new_dividends, $market_data);

            // sync last dividend amount to market data table
            $market_data->last_dividend_amount = $dividend_data->sortByDesc('date')->first()['dividend_amount'];
            $market_data->save();
        }
    }

    /**
     * Updates the dividends earned by every holding of a symbol
     */
    public static function syncHoldings(string $symbol): void
    {
        $received = self::symbol($symbol)
            ->withSharesOwned()
            ->get()
            ->groupBy('portfolio_id')
            ->map->sum('total_received');

        Holding::where(['symbol' => $symbol])
            ->get()
            ->each(function ($holding) use ($received) {
                $holding->update([
                    'dividends_earned' => $received->get($holding->portfolio_id, 0),
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
                        'currency' => $holding->market_data->currency,
                        'transaction_type' => 'BUY',
                        'reinvested_dividend' => true,
                        'cost_basis' => 0,
                        'quantity' => ($dividend['dividend_amount'] * $holding->qtyOwned(Carbon::parse($dividend['date']))) / $market_data->market_value,
                    ]);
                }
            });
    }
}
