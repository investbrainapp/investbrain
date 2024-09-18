<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tschucki\Alphavantage\Facades\Alphavantage;

class NasdaqMarketData implements MarketDataInterface
{

    public function exists(String $symbol): Bool
    {

        return $this->quote($symbol)->isNotEmpty();
    }

    public function quote(String $symbol): Collection
    {
        // https://api.nasdaq.com/api/quote/GOOG/info?assetclass=stocks

        $quote = Alphavantage::core()->quoteEndpoint($symbol);
        $quote = Arr::get($quote, 'Global Quote', []);

        // https://api.nasdaq.com/api/quote/GOOG/summary?assetclass=stocks

        $fundamental = cache()->tags(['quote', 'alpha-vantage', $symbol])->remember(
            'symbol-'.$symbol, 
            1440, 
            function () use ($symbol) {
                return Alphavantage::fundamentals()->overview($symbol);
            }
        );

        if (empty($fundamental)) return collect();

        return collect([
            'name' => Arr::get($fundamental, 'Name'),
            'symbol' => Arr::get($fundamental, 'Symbol'),
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
                        : null
        ]);        
    }

    public function dividends(String $symbol, $startDate, $endDate): Collection
    {
        // https://api.nasdaq.com/api/quote/GOOG/dividends?assetclass=stocks

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
        throw new \Exception('The Nasdaq provider does not offer a splits endpoint.');
    }

    public function history(String $symbol, $startDate, $endDate): Collection
    {

        // https://api.nasdaq.com/api/quote/GOOG/historical?assetclass=stocks&fromdate=2014-09-16&limit=2000&offset=10&todate=2024-09-16
        // https://api.nasdaq.com/api/quote/GOOG/chart?assetclass=stocks&fromdate=2014-09-16&todate=2024-09-16

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

    public function nasdaqClient($symbol, $method, $params = [], $retry = false): Array|Object
    // protected function nasdaqClient($symbol, $method, $params = [], $retry = false): Array|Object
    {

        $symbol = strtoupper($symbol);
        $params = array_merge([
            'assetclass' => 'stocks'
        ], $params);

        if (!in_array($method, ['info', 'summary', 'dividends', 'historical', 'chart'])) {

            throw new \Exception('This is not a valid method.');
        }

        $endpoint = 'https://api.nasdaq.com/api/quote';

        // return [url("$endpoint/$symbol/$method?assetclass=stock", $params), $params];

        $response = Http::get("https://api.nasdaq.com/api/quote/$symbol/$method?assetclass=stock", $params)->json();

        // if ($response->status->rCode != 200) {
            
        //     if ($retry == true) {
        //         throw new \Exception("Couldn't resolve $method for $symbol from Nasdaq.");
        //     }

        //     return $this->nasdaqClient($symbol, $method, array_merge($params, [ 'assetclass' => 'etf' ]), retry: true);
        // }

        return $response;
    }
}