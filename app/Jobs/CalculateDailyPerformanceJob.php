<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BackupExport;
use App\Models\DailyChange;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Calculates one portfolio's daily performance figures for a backup export and stores them as
 * JSON. The figures are slow to calculate, so each portfolio gets its own (parallel) job.
 */
class CalculateDailyPerformanceJob implements ShouldQueue
{
    use Batchable;
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
    public $timeout = 300;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Skip the job if the export was replaced (and deleted) before it ran.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BackupExport $backupExport,
        public string $portfolioId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // the figures are in the user's display currency
        Auth::setUser($this->backupExport->user);

        try {
            $performance = DailyChange::where('daily_change.portfolio_id', $this->portfolioId)
                ->withDailyPerformance()
                ->get()
                ->mapWithKeys(fn (DailyChange $dailyChange) => [
                    $dailyChange->date->toDateString() => [
                        'total_cost_basis' => $dailyChange->total_cost_basis,
                        'realized_gains' => $dailyChange->realized_gain_dollars,
                        'total_dividends_earned' => $dailyChange->total_dividends_earned,
                    ],
                ]);

            Storage::disk()->put($this->backupExport->performancePath($this->portfolioId), $performance->toJson());
        } finally {
            // queue workers are long lived, so don't leak the user into the next job
            Auth::forgetUser();
        }
    }

    /**
     * Handle a job failure. The batch is cancelled, so the export is never assembled.
     */
    public function failed(?Throwable $exception): void
    {
        $this->backupExport->markAsFailed();
    }
}
