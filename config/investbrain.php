<?php

declare(strict_types=1);

return [

    'refresh' => env('MARKET_DATA_REFRESH', 30), // minutes

    'provider' => env('MARKET_DATA_PROVIDER', 'yahoo'),

    'interfaces' => [
        'yahoo' => App\Interfaces\MarketData\YahooMarketData::class,
        'alphavantage' => App\Interfaces\MarketData\AlphaVantageMarketData::class,
        'finnhub' => App\Interfaces\MarketData\FinnhubMarketData::class,
        'fake' => App\Interfaces\MarketData\FakeMarketData::class,
    ],

    'self_hosted' => env('SELF_HOSTED', true),

    'daily_change_time_of_day' => env('DAILY_CHANGE_TIME', '23:00'),

    'base_currency' => env('BASE_CURRENCY', 'USD'),

    'currency_aliases' => [
        'GBP' => ['currency' => 'GBX', 'label' => 'British Sterling Pence', 'adjustment' => 100],
        'ZAR' => ['currency' => 'ZAC', 'label' => 'South Africa Rand Cent', 'adjustment' => 100],
    ],
];
