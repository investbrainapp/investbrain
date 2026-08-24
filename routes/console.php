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
 * Note: Update the cadence with the MARKET_DATA_REFRESH key in your env file (default: 30 minutes)
 */
Schedule::command(RefreshMarketData::class)->weekdays()->everyMinute();

/**
 * This scheduled job records daily changes to your portfolios every weekday
 * Note: Update the time of day with the DAILY_CHANGE_TIME key in your env file (default: 23:00)
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
 * Refreshes currency exchange data daily
 */
Schedule::command(RefreshCurrencyData::class)->daily();
