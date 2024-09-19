<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FakeMarketData implements MarketDataInterface
{
    public function exists(String $symbol): Bool
    {

        return true;
    }

    public function quote(String $symbol): Collection
    {

        return collect([
            'name' => 'ACME Company Ltd',
            'symbol' => $symbol,
            'market_value' => (float) 230.19,
            'fifty_two_week_high' => (float) 512.90,
            'fifty_two_week_low' => (float) 341.20,
            'forward_pe' => (float) 20.1,
            'trailing_pe' => (float) 30.34,
            'market_cap' => (int) 9800700600,
            'book_value' => (float) 4.7,
            'last_dividend_date' => now()->subDays(45),
            'dividend_yield' => (float) 0.033
        ]);
    }

    public function dividends(String $symbol, $startDate, $endDate): Collection
    {

        return collect([
            [
                'symbol' => $symbol,
                'date' => now()->subMonths(3)->format('Y-m-d H:i:s'),
                'dividend_amount' => 2.11,
            ],
            [
                'symbol' => $symbol,
                'date' => now()->subMonths(6)->format('Y-m-d H:i:s'),
                'dividend_amount' => 1.89,
            ],
            [
                'symbol' => $symbol,
                'date' => now()->subMonths(9)->format('Y-m-d H:i:s'),
                'dividend_amount' => 0.95,
            ],
        ]);
    }

    public function splits(String $symbol, $startDate, $endDate): Collection
    {   

        return collect([
            [
                'symbol' => $symbol,
                'date' => now()->subMonths(36)->format('Y-m-d H:i:s'),
                'split_amount' => 10,
            ],
        ]);
    }

    public function history(String $symbol, $startDate, $endDate): Collection
    {
        $numDays = Carbon::parse($startDate)->diffInDays($endDate, true);

        for ($i = 0; $i < $numDays; $i++) { 

            $date = now()->subDays($i)->format('Y-m-d');

            $series[$date] = [
                'symbol' => $symbol,
                'date' => $date,
                'close' => (float) rand(150, 400),
            ];
        }
        
        return collect($series);
    }
}