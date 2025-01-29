<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Dividend;
use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\Types\Split;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tschucki\Alphavantage\Facades\Alphavantage;

class AlphaVantageMarketData implements MarketDataInterface
{
    public function exists(string $symbol): bool
    {

        try {
            $this->quote($symbol);

            return true;

        } catch (\Throwable $e) {

            return false;
        }
    }

    public function quote(string $symbol): Quote
    {
        $quote = Alphavantage::core()->quoteEndpoint($symbol);
        $quote = Arr::get($quote, 'Global Quote', []);

        $fundamental = cache()->remember(
            'av-symbol-'.$symbol,
            1440,
            function () use ($symbol) {
                return Alphavantage::fundamentals()->overview($symbol);
            }
        );

        return new Quote([
            'name' => Arr::get($fundamental, 'Name'),
            'symbol' => $symbol,
            'market_value' => Arr::get($quote, '05. price'),
            'fifty_two_week_high' => Arr::get($fundamental, '52WeekHigh'),
            'fifty_two_week_low' => Arr::get($fundamental, '52WeekLow'),
            'forward_pe' => Arr::get($fundamental, 'ForwardPE'),
            'trailing_pe' => Arr::get($fundamental, 'TrailingPE'),
            'market_cap' => Arr::get($fundamental, 'MarketCapitalization'),
            'book_value' => Arr::get($fundamental, 'BookValue'),
            'last_dividend_date' => Arr::get($fundamental, 'DividendDate') != 'None'
                        ? Arr::get($fundamental, 'DividendDate')
                        : null,
            'dividend_yield' => Arr::get($fundamental, 'DividendYield') != 'None'
                        ? Arr::get($fundamental, 'DividendYield')
                        : null,
        ]);
    }

    public function dividends(string $symbol, $startDate, $endDate): Collection
    {
        $dividends = Alphavantage::fundamentals()->dividends($symbol);
        $dividends = Arr::get($dividends, 'data', []);

        return collect($dividends)
            ->filter(function ($dividend) use ($startDate, $endDate) {

                return Carbon::parse(Arr::get($dividend, 'ex_dividend_date'))->between($startDate, $endDate);
            })
            ->map(function ($dividend) use ($symbol) {

                return new Dividend([
                    'symbol' => $symbol,
                    'date' => Carbon::parse(Arr::get($dividend, 'ex_dividend_date')),
                    'dividend_amount' => Arr::get($dividend, 'amount'),
                ]);
            });
    }

    public function splits(string $symbol, $startDate, $endDate): Collection
    {
        $splits = Alphavantage::fundamentals()->splits($symbol);
        $splits = Arr::get($splits, 'data', []);

        return collect($splits)
            ->filter(function ($split) use ($startDate, $endDate) {

                return Carbon::parse(Arr::get($split, 'effective_date'))->between($startDate, $endDate);
            })
            ->map(function ($split) use ($symbol) {

                return new Split([
                    'symbol' => $symbol,
                    'date' => Carbon::parse(Arr::get($split, 'effective_date')),
                    'split_amount' => Arr::get($split, 'split_factor'),
                ]);
            });
    }

    public function history(string $symbol, $startDate, $endDate): Collection
    {

        $history = Alphavantage::timeSeries()->daily($symbol, 'full');

        $history = Arr::get($history, 'Time Series (Daily)', []);

        return collect($history)
            ->filter(function ($history, $date) use ($startDate, $endDate) {

                return Carbon::parse($date)->between($startDate, $endDate);
            })
            ->mapWithKeys(function ($history, $date) use ($symbol) {

                $date = Carbon::parse($date)->format('Y-m-d');

                return [$date => new Ohlc([
                    'symbol' => $symbol,
                    'date' => $date,
                    'close' => Arr::get($history, '4. close'),
                ])];
            });
    }
}
