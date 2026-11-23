<?php

declare(strict_types=1);
use App\Interfaces\MarketData\AlpacaMarketData;
use App\Interfaces\MarketData\AlphaVantageMarketData;
use App\Interfaces\MarketData\FakeMarketData;
use App\Interfaces\MarketData\FinnhubMarketData;
use App\Interfaces\MarketData\TwelveDataMarketData;
use App\Interfaces\MarketData\YahooMarketData;

return [

    'refresh' => env('MARKET_DATA_REFRESH', 30), // minutes

    'provider' => env('MARKET_DATA_PROVIDER', 'yahoo'),

    'interfaces' => [
        'yahoo' => YahooMarketData::class,
        'alphavantage' => AlphaVantageMarketData::class,
        'alpaca' => AlpacaMarketData::class,
        'finnhub' => FinnhubMarketData::class,
        'twelvedata' => TwelveDataMarketData::class,
        'fake' => FakeMarketData::class,
    ],

    'self_hosted' => env('SELF_HOSTED', true),

    'version' => env('VERSION'), // set by the Docker image at build time

    'daily_change_time_of_day' => env('DAILY_CHANGE_TIME', '23:00'),

    'base_currency' => env('BASE_CURRENCY', 'USD'),

    'currency_aliases' => [
        'RMB' => ['alias_of' => 'CNY', 'label' => 'Chinese Yuan (Renminbi)', 'adjustment' => 1],
        'GBX' => ['alias_of' => 'GBP', 'label' => 'British Sterling Pence', 'adjustment' => 100],
        'ZAC' => ['alias_of' => 'ZAR', 'label' => 'South Africa Rand Cent', 'adjustment' => 100],
    ],
];
