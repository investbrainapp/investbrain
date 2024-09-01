<?php

return [

    'refresh' => env('MARKET_DATA_REFRESH', 30), // minutes

    'default' => env('MARKET_DATA_PROVIDER', 'yahoo'),

    'yahoo' => App\Interfaces\MarketData\YahooMarketData::class,
    'alphavantage' => App\Interfaces\MarketData\AlphaVantageMarketData::class,
    'fake' => App\Interfaces\MarketData\FakeMarketData::class,
];