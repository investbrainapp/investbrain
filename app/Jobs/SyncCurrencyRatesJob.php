<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CurrencyRate;
use App\Models\Transaction;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCurrencyRatesJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    public int $chunk_size = 250;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $min_date = Transaction::min('date');

        collect(CarbonPeriod::create($min_date, now()))
            ->chunk($this->chunk_size)
            ->map(function ($chunk) {

                // get chunks for min and max dates
                return collect([
                    'min' => $chunk->min()->toDateString(),
                    'max' => $chunk->max()->toDateString(),
                ]);
            })
            ->each(function ($chunk) {

                // sync in chunks
                CurrencyRate::timeSeriesRates(
                    '', // use fake currency to force
                    $chunk->get('min'),
                    $chunk->get('max')
                );
            });
    }
}
