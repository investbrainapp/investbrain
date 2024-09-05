<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\{
    RefreshMarketData,
    CaptureDailyChange,
    RefreshDividendData,
    RefreshSplitData
};

Schedule::command(RefreshMarketData::class)->weekdays()->everyMinute(); // configurable in 'config.market_data'
Schedule::command(CaptureDailyChange::class)->weekdays();
Schedule::command(RefreshDividendData::class)->weekly();
Schedule::command(RefreshSplitData::class)->monthly();
