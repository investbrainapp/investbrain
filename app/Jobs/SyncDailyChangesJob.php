<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Portfolio;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Rebuilds a portfolio's daily change history from its current transactions. Each portfolio has
 * at most one sync waiting and one running: while one is waiting, dispatching another does nothing,
 * since the waiting one will see the new data when it runs.
 */
class SyncDailyChangesJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 900;

    /**
     * How long a waiting sync blocks another from being queued, in case it's never processed.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    public function __construct(
        public Portfolio $portfolio
    ) {
        // wait for the write that triggered the sync to commit, so the sync sees it
        $this->afterCommit();
    }

    /**
     * One waiting sync per portfolio.
     */
    public function uniqueId(): string
    {
        return $this->portfolio->id;
    }

    /**
     * One running sync per portfolio; a waiting one is retried until the running one finishes.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->portfolio->id))
                ->releaseAfter(5)
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * Keep retrying while a running sync finishes, rather than failing after a few attempts.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->portfolio->syncDailyChanges();

        cache()->forget('portfolio-metrics-'.$this->portfolio->id);
    }
}
