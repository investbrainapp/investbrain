<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinnhubMarketData implements MarketDataInterface
{
    public \Finnhub\Api\DefaultApi $client;

    public function __construct()
    {
        
        $this->client = new \Finnhub\Api\DefaultApi(
            new \GuzzleHttp\Client(),
            \Finnhub\Configuration::getDefaultConfiguration()->setApiKey('token', config('finnhub.key'))
        );
    }
    public function exists(String $symbol): Bool
    {

        return $this->quote($symbol)->isNotEmpty();
    }

    public function quote($symbol): Collection
    {


        $quote = $this->client->quote($symbol);
    
        $fundamental = cache()->remember(
            'fh-symbol-'.$symbol, 
            1440, 
            function () use ($symbol) {
                return $this->client->companyBasicFinancials($symbol, "all");
            }
        );
    
        if (empty($fundamental)) return collect();
    
        return collect([
            'name' => Arr::get($fundamental, 'metric.name'),
            'symbol' => $symbol,
            'market_value' => (float) Arr::get($quote, 'c'), 
            'fifty_two_week_high' => (float) Arr::get($fundamental, 'metric.52WeekHigh'),
            'fifty_two_week_low' => (float) Arr::get($fundamental, 'metric.52WeekLow'),
            'forward_pe' => (float) Arr::get($fundamental, 'metric.forwardPE'), // confirm
            'trailing_pe' => (float) Arr::get($fundamental, 'metric.trailingPE'), // confirm
            'market_cap' => (int) Arr::get($fundamental, 'metric.marketCapitalization'), // confirm
            'book_value' => (float) Arr::get($fundamental, 'metric.bookValuePerShare'), // confirm
            'last_dividend_date' => Arr::get($fundamental, 'metric.lastDivDate'), // confirm
            'dividend_yield' => (float) Arr::get($fundamental, 'metric.dividendYield'), // confirm
        ]);      
    }

    public function dividends($symbol, $startDate, $endDate): Collection
    {
        $dividends = $this->client->stockDividends($symbol, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
        
        return collect($dividends)->map(function($dividend) use ($symbol) {
                            
            return [
                'symbol' => $symbol,
                'date' => Carbon::parse(Arr::get($dividend, 'date'))
                                    ->format('Y-m-d H:i:s'),
                'dividend_amount' => Arr::get($dividend, 'amount'),
            ];
        });
    }

    public function splits($symbol, $startDate, $endDate): Collection
    {   

        $splits = $this->client->stockSplits($symbol, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

        return collect($splits)->map(function($split) use ($symbol) {
            
            return [
                'symbol' => $symbol,
                'date' => Carbon::parse(Arr::get($split, 'date'))
                                    ->format('Y-m-d H:i:s'),
                'split_amount' => Arr::get($split, 'toFactor') / Arr::get($split, 'fromFactor'),
            ];
        });
    }

    public function history($symbol, $startDate, $endDate): Collection
    {

        $history = $this->client->stockCandles($symbol, "D", $startDate->timestamp, $endDate->timestamp);

        $timestamps = Arr::get($history, 't', []);
        $closes = Arr::get($history, 'c', []);

        return collect($timestamps)->mapWithKeys(function ($timestamp, $index) use ($symbol, $closes) {
            $date = Carbon::createFromTimestamp($timestamp)->format('Y-m-d');
            return [ $date => [
                'symbol' => $symbol,
                'date' => $date,
                'close' => (float) $closes[$index],
            ]];
        });
    }
}