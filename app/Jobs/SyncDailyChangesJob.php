<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Portfolio;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Rebuilds a portfolio's daily change history. Only one runs per portfolio at a time, and the
 * newest one wins: dispatching another makes any older one, queued or running, stop early, because
 * the newer one will rebuild the same history from scratch.
 */
class SyncDailyChangesJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 900;

    public function __construct(
        public Portfolio $portfolio,
        public string $token
    ) {}

    /**
     * Queue a sync for the portfolio, replacing any sync already queued or running for it.
     */
    public static function dispatchFor(Portfolio $portfolio): void
    {
        $token = (string) Str::uuid();

        Cache::put(self::tokenKey($portfolio->id), $token, now()->addDay());

        // wait for the write that triggered the sync to commit, so the sync sees it
        self::dispatch($portfolio, $token)->afterCommit();
    }

    /**
     * Run one sync per portfolio at a time; a newer one waits until the older one has stopped.
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
     * Keep retrying while an older sync finishes stopping, rather than failing after a few attempts.
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
        if ($this->isSuperseded()) {
            return;
        }

        $this->portfolio->syncDailyChanges(fn () => $this->isSuperseded());

        cache()->forget('portfolio-metrics-'.$this->portfolio->id);
    }

    /**
     * Determine if a newer sync has been dispatched for this portfolio.
     */
    public function isSuperseded(): bool
    {
        return Cache::get(self::tokenKey($this->portfolio->id)) !== $this->token;
    }

    protected static function tokenKey(string $portfolioId): string
    {
        return 'daily-change-sync:'.$portfolioId;
    }
}
