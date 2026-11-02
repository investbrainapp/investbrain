<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Dividend;
use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Follow up work after an import that depends on the market data provider. Each step
 * is best effort so one bad symbol (or a provider outage) can't leave holdings half synced.
 */
class SyncImportedHoldingsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 900;

    /**
     * @param  list<string>  $portfolioIds
     * @param  list<string>  $symbols
     */
    public function __construct(
        public array $portfolioIds,
        public array $symbols
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->symbols as $symbol) {
            try {
                Dividend::refreshDividendData($symbol);
            } catch (Throwable $e) {
                Log::warning('Could not refresh dividends for '.$symbol.' after import: '.$e->getMessage());
            }
        }

        // dividends earned depends on the dividends we just refreshed
        Holding::whereIn('portfolio_id', $this->portfolioIds)
            ->whereIn('symbol', $this->symbols)
            ->get()
            ->each(fn (Holding $holding) => $holding->syncTransactionsAndDividends());

        Portfolio::whereIn('id', $this->portfolioIds)->get()->each(function (Portfolio $portfolio) {
            try {
                $portfolio->syncDailyChanges();
            } catch (Throwable $e) {
                Log::warning('Could not sync daily changes for portfolio '.$portfolio->id.' after import: '.$e->getMessage());
            }

            cache()->forget('portfolio-metrics-'.$portfolio->id);
        });
    }
}
