<?php

namespace App\Interfaces\MarketData;

use Illuminate\Support\Collection;

class FakeMarketData implements MarketDataInterface
{
    public function exists(String $symbol): Bool
    {

        return true;
    }

    public function quote($symbol): Collection
    {

        return collect([
            'name' => 'ACME Company Ltd',
            'symbol' => $symbol,
            'market_value' => 230.19,
            'fifty_two_week_high' => 512.90,
            'fifty_two_week_low' => 341.20,
            'forward_pe' => 20.1,
            'trailing_pe' => 30.34,
            'market_cap' => 9800700600,
            'book_value' => 4.7,
            'last_dividend_date' => now()->subDays(45),
            'dividend_yield' => .033
        ]);
    }

    public function dividends($symbol, $startDate, $endDate): Collection
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

    public function splits($symbol, $startDate, $endDate): Collection
    {   

        return collect([
            [
                'symbol' => $symbol,
                'date' => now()->subMonths(36)->format('Y-m-d H:i:s'),
                'split_amount' => 10,
            ],
        ]);
    }

    public function history($symbol, $startDate, $endDate): Collection
    {

        for ($i = 0; $i < 14; $i++) { 

            $series[] = [
                'symbol' => $symbol,
                'date' => now()->subDays($i)->format('Y-m-d'),
                'close' => (float) rand(1, 100),
            ];
        }
        
        return collect($series);
    }
}