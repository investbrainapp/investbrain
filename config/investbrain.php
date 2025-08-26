<?php

declare(strict_types=1);

return [

    'refresh' => env('MARKET_DATA_REFRESH', 30), // minutes

    'provider' => env('MARKET_DATA_PROVIDER', 'yahoo'),

    'interfaces' => [
        'yahoo' => App\Interfaces\MarketData\YahooMarketData::class,
        'alphavantage' => App\Interfaces\MarketData\AlphaVantageMarketData::class,
        'alpaca' => App\Interfaces\MarketData\AlpacaMarketData::class,
        'finnhub' => App\Interfaces\MarketData\FinnhubMarketData::class,
        'twelvedata' => App\Interfaces\MarketData\TwelveDataMarketData::class,
        'fake' => App\Interfaces\MarketData\FakeMarketData::class,
    ],

    'self_hosted' => env('SELF_HOSTED', true),

    'daily_change_time_of_day' => env('DAILY_CHANGE_TIME', '23:00'),

    'base_currency' => env('BASE_CURRENCY', 'USD'),

    'currency_aliases' => [
        'RMB' => ['alias_of' => 'CNY', 'label' => 'Chinese Yuan (Renminbi)', 'adjustment' => 1],
        'GBX' => ['alias_of' => 'GBP', 'label' => 'British Sterling Pence', 'adjustment' => 100],
        'ZAC' => ['alias_of' => 'ZAR', 'label' => 'South Africa Rand Cent', 'adjustment' => 100],
    ],
];
