<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tschucki\Alphavantage\Facades\Alphavantage;

class AlphaVantageMarketData implements MarketDataInterface
{
    public function exists(String $symbol): Bool
    {

        return $this->quote($symbol)->isNotEmpty();
    }

    public function quote(String $symbol): Collection
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

        return collect([
            'name' => Arr::get($fundamental, 'Name'),
            'symbol' => Arr::get($fundamental, 'Symbol'),
            'market_value' => (float) Arr::get($quote, '05. price'),
            'fifty_two_week_high' => (float) Arr::get($fundamental, '52WeekHigh'),
            'fifty_two_week_low' => (float) Arr::get($fundamental, '52WeekLow'),
            'forward_pe' => (float) Arr::get($fundamental, 'ForwardPE'),
            'trailing_pe' => (float) Arr::get($fundamental, 'TrailingPE'),
            'market_cap' => (int) Arr::get($fundamental, 'MarketCapitalization'),
            'book_value' => (float) Arr::get($fundamental, 'BookValue'),
            'last_dividend_date' => Arr::get($fundamental, 'DividendDate') != 'None'
                        ? Arr::get($fundamental, 'DividendDate')
                        : null,
            'dividend_yield' => Arr::get($fundamental, 'DividendYield') != 'None'
                        ? (float) Arr::get($fundamental, 'DividendYield')
                        : null
        ]);        
    }

    public function dividends(String $symbol, $startDate, $endDate): Collection
    {
        $dividends = Alphavantage::fundamentals()->dividends($symbol);
        $dividends = Arr::get($dividends, 'data', []);

        return collect($dividends)
                        ->filter(function($dividend) use ($startDate, $endDate) {
                            
                            return Carbon::parse(Arr::get($dividend, 'ex_dividend_date'))->between($startDate, $endDate);
                        })
                        ->map(function($dividend) use ($symbol) {
                            
                            return [
                                'symbol' => $symbol,
                                'date' => Carbon::parse(Arr::get($dividend, 'ex_dividend_date'))
                                                    ->format('Y-m-d H:i:s'),
                                'dividend_amount' => Arr::get($dividend, 'amount'),
                            ];
                        });
    }

    public function splits(String $symbol, $startDate, $endDate): Collection
    {   
        $splits = Alphavantage::fundamentals()->splits($symbol);
        $splits = Arr::get($splits, 'data', []);

        return collect($splits)
                        ->filter(function($split) use ($startDate, $endDate) {
                                            
                            return Carbon::parse(Arr::get($split, 'effective_date'))->between($startDate, $endDate);
                        })
                        ->map(function($split) use ($symbol) {
                            
                            return [
                                'symbol' => $symbol,
                                'date' => Carbon::parse(Arr::get($split, 'effective_date'))
                                                    ->format('Y-m-d H:i:s'),
                                'split_amount' => Arr::get($split, 'split_factor'),
                            ];
                        });
    }

    public function history(String $symbol, $startDate, $endDate): Collection
    {

        $history = Alphavantage::timeSeries()->daily($symbol, 'full');

        $history = Arr::get($history, 'Time Series (Daily)', []);
        
        return collect($history)
                    ->filter(function ($history, $date) use ($startDate, $endDate) {

                        return Carbon::parse($date)->between($startDate, $endDate);
                    })
                    ->mapWithKeys(function($history, $date) use ($symbol) {

                        $date = Carbon::parse($date)->format('Y-m-d');
                        
                        return [ $date => [
                                'symbol' => $symbol,
                                'date' => $date,
                                'close' => (float) Arr::get($history, '4. close')
                            ]];
                    });
    }
}