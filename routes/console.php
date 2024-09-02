<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\{
    RefreshMarketData,
    CaptureDailyChange,
    RefreshDividendData,
    RefreshSplitData
};

Schedule::call(RefreshMarketData::class)->weekdays()->everyMinute(); // configurable in 'config.market_data'
Schedule::call(CaptureDailyChange::class)->weekdays();
Schedule::call(RefreshDividendData::class)->weekly();
Schedule::call(RefreshSplitData::class)->monthly();
