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

    protected int $chunk_size = 250;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $date = Transaction::query()
            ->selectRaw('min(date)')
            ->first();

        collect(CarbonPeriod::create($date->min, now()))
            ->chunk($this->chunk_size)
            ->map(function ($chunk) {
                return collect([
                    'min' => $chunk->min()->toDateString(),
                    'max' => $chunk->max()->toDateString(),
                ]);
            })
            ->each(function ($chunk) {
                CurrencyRate::timeSeriesRates(
                    'ZZZ', // use fake currency to force
                    $chunk->get('min'),
                    $chunk->get('max')
                );
            });
    }
}
