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

    'version' => env('VERSION', 'dev'),

    /**
     * Anonymous usage statistics sent by self-hosted instances each time the Docker container starts.
     * Nothing is sent when self_hosted is false. See docs/self-hosting.md#telemetry for what's included.
     */
    'telemetry' => [
        'enabled' => env('TELEMETRY_ENABLED', true),
        'url' => env('TELEMETRY_URL', 'https://investbra.in/telemetry'),
        'instance_id_path' => storage_path('app/.instance-id'),
    ],

    'daily_change_time_of_day' => env('DAILY_CHANGE_TIME', '23:00'),

    'base_currency' => env('BASE_CURRENCY', 'USD'),

    'currency_aliases' => [
        'RMB' => ['alias_of' => 'CNY', 'label' => 'Chinese Yuan (Renminbi)', 'adjustment' => 1],
        'GBX' => ['alias_of' => 'GBP', 'label' => 'British Sterling Pence', 'adjustment' => 100],
        'ZAC' => ['alias_of' => 'ZAR', 'label' => 'South Africa Rand Cent', 'adjustment' => 100],
    ],
];
