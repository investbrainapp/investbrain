<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Dividend;
use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\Types\Split;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class FakeMarketData implements MarketDataInterface
{
    public function exists(string $symbol): bool
    {

        return true;
    }

    public function quote(string $symbol): Quote
    {

        return new Quote([
            'name' => 'ACME Company Ltd',
            'symbol' => $symbol,
            'currency' => 'USD',
            'market_value' => 230.19,
            'fifty_two_week_high' => 512.90,
            'fifty_two_week_low' => 341.20,
            'forward_pe' => 20.1,
            'trailing_pe' => 30.34,
            'market_cap' => 9800700600,
            'book_value' => 4.7,
            'last_dividend_date' => now()->subDays(45),
            'dividend_yield' => 0.033,
            'meta_data' => [],
        ]);
    }

    public function dividends(string $symbol, $startDate, $endDate): Collection
    {

        return collect([
            new Dividend([
                'symbol' => $symbol,
                'date' => now()->subMonths(3),
                'dividend_amount' => 2.11,
            ]),
            new Dividend([
                'symbol' => $symbol,
                'date' => now()->subMonths(6),
                'dividend_amount' => 1.89,
            ]),
            new Dividend([
                'symbol' => $symbol,
                'date' => now()->subMonths(9),
                'dividend_amount' => 0.95,
            ]),
        ]);
    }

    public function splits(string $symbol, $startDate, $endDate): Collection
    {

        return collect([
            new Split([
                'symbol' => $symbol,
                'date' => now()->subMonths(36),
                'split_amount' => 10,
            ]),
        ]);
    }

    public function history(string $symbol, $startDate, $endDate): Collection
    {

        $days = CarbonPeriod::create($startDate, $endDate)->filter('isWeekday');

        $countOfDays = $days->count();

        foreach ($days as $index => $date) {

            $date = $date->toDateString();

            $series[$date] = new Ohlc([
                'symbol' => $symbol,
                'date' => $date,
                'open' => rand(150, 400),
                'high' => rand(150, 400),
                'low' => rand(150, 400),
                'close' => $index == $countOfDays - 1
                    ? 230.19 // most recent close should match current market value
                    : rand(150, 400),
            ]);
        }

        return collect($series);
    }
}
