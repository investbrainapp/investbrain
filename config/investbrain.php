<?php

return [

    'refresh' => env('MARKET_DATA_REFRESH', 30), // minutes

    'provider' => env('MARKET_DATA_PROVIDER', 'yahoo'),

    'interfaces' => [
        'yahoo' => App\Interfaces\MarketData\YahooMarketData::class,
        'alphavantage' => App\Interfaces\MarketData\AlphaVantageMarketData::class,
        'finnhub' => App\Interfaces\MarketData\FinnhubMarketData::class,
        'fake' => App\Interfaces\MarketData\FakeMarketData::class,
    ],

    'self_hosted' => env('SELF_HOSTED', true)
];