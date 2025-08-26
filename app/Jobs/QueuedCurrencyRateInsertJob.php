<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CurrencyRate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class QueuedCurrencyRateInsertJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    public function __construct(
        protected array $chunk
    ) {
        $this->chunk = $chunk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        CurrencyRate::insertOrIgnore($this->chunk);
    }
}
