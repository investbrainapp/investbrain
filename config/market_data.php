<?php

return [

    'refresh' => 30, // minutes

    'default' => env('MARKET_DATA_PROVIDER', 'yahoo'),

    'yahoo' => App\Interfaces\MarketData\YahooMarketData::class,
    // 'fake' => App\Interfaces\MarketData\FakeMarketData::class,
];