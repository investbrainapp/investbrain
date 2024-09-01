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

        $dividends = Alphavantage::fundamentals()->dividends($symbol);

        return collect($dividends)
                        ->where('ex_dividend_date', '>=', $startDate)
                        ->where('ex_dividend_date', '<', $endDate)
                        ->map(function($dividend) use ($symbol) {
                            
                            return [
                                'symbol' => $symbol,
                                'date' => Carbon::parse(Arr::get($dividend, 'ex_dividend_date'))
                                                    ->format('Y-m-d H:i:s'),
                                'dividend_amount' => Arr::get($dividend, 'amount'),
                            ];
                        });
    }

    public function splits($symbol, $startDate, $endDate): Collection
    {   

        $splits = Alphavantage::fundamentals()->splits($symbol);

        return collect($splits)
                        ->where('effective_date', '>=', $startDate)
                        ->where('effective_date', '<', $endDate)
                        ->map(function($split) use ($symbol) {
                            
                            return [
                                'symbol' => $symbol,
                                'date' => Carbon::parse(Arr::get($split, 'effective_date'))
                                                    ->format('Y-m-d H:i:s'),
                                'split_amount' => Arr::get($split, 'split_factor'),
                            ];
                        });
    }
}