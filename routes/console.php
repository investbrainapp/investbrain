<?php

declare(strict_types=1);

use App\Console\Commands\CaptureDailyChange;
use App\Console\Commands\RefreshCurrencyData;
use App\Console\Commands\RefreshDividendData;
use App\Console\Commands\RefreshMarketData;
use App\Console\Commands\RefreshSplitData;
use App\Console\Commands\SyncHoldingData;
use Illuminate\Support\Facades\Schedule;

/**
 * This scheduled job refreshes market data from your selected data provider
 * Update the cadence with the MARKET_DATA_REFRESH key in your env file
 */
Schedule::command(RefreshMarketData::class)->weekdays()->everyMinute();

/**
 * This scheduled job records daily changes to your portfolios every weekday
 */
Schedule::command(CaptureDailyChange::class)->dailyAt(config('investbrain.daily_change_time_of_day'))->weekdays();

/**
 * Refreshes dividend data for your holdings (and syncs new dividends to holdings)
 */
Schedule::command(RefreshDividendData::class)->daily()->days([1, 3, 5]);

/**
 * Refreshes split data for your holdings (and creates new transactions for new splits)
 */
Schedule::command(RefreshSplitData::class)->weekly();

/**
 * Periodically reconciles your holdings with transactions and dividends
 */
Schedule::command(SyncHoldingData::class)->yearly();

/**
 * Refreshes currency exchange data several times daily
 */
Schedule::command(RefreshCurrencyData::class)->daily()->everySixHours();
