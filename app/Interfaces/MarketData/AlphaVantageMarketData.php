<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Tschucki\Alphavantage\Facades\Alphavantage;

class AlphaVantageMarketData implements MarketDataInterface
{
    public function exists(String $symbol): Bool
    {

        return $this->quote($symbol)->isNotEmpty();
    }

    public function quote($symbol): Collection
    {
        $fundamental = Alphavantage::fundamentals()->overview($symbol);
        $quote = Alphavantage::core()->quoteEndpoint($symbol);

        if (empty($fundamental)) return collect();

        return collect([
            'name' => Arr::get($fundamental, 'Name'),
            'symbol' => Arr::get($fundamental, 'Symbol'),
            'market_value' => Arr::get($quote, 'Global Quote')['05. price'],
            'fifty_two_week_high' => Arr::get($fundamental, '52WeekHigh'),
            'fifty_two_week_low' => Arr::get($fundamental, '52WeekLow'),
            'forward_pe' => Arr::get($fundamental, 'ForwardPE'),
            'trailing_pe' => Arr::get($fundamental, 'TrailingPE'),
            'market_cap' => Arr::get($fundamental, 'MarketCapitalization') 
        ]);        
    }

    public function dividends($symbol, $startDate, $endDate): Collection
    {

        return collect($this->client->getHistoricalDividendData($symbol, $startDate, $endDate))
                        ->map(function($dividend) use ($symbol) {
                            
                            return [
                                'symbol' => $symbol,
                                'date' => $dividend->getDate()->format('Y-m-d H:i:s'),
                                'dividend_amount' => $dividend->getDividends(),
                            ];
                        });
    }

    public function splits($symbol, $startDate, $endDate): Collection
    {   

        return collect($this->client->getHistoricalSplitData($symbol, $startDate, $endDate))
                        ->map(function($split) use ($symbol) {
                            $split_amount = explode(':', $split->getStockSplits());

                            return [
                                'symbol' => $symbol,
                                'date' => $split->getDate()->format('Y-m-d H:i:s'),
                                'split_amount' => $split_amount[0] / $split_amount[1],
                            ];
                        });
    }
}