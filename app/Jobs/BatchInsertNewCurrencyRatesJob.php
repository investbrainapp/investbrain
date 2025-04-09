<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CurrencyRate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BatchInsertNewCurrencyRatesJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    public int $chunk_size = 100;

    public function __construct(
        protected array $updates
    ) {
        $this->updates = $updates;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $chunks = array_chunk($this->updates, $this->chunk_size);

        foreach ($chunks as $chunk) {
            CurrencyRate::insertOrIgnore($chunk);
        }

    }
}
