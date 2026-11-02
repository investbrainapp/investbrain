<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BackupExport as BackupExportModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Starts building a backup in the background (away from the web request's time and memory
 * limits): first each portfolio's performance figures, then the workbook itself.
 */
class BackupExportJob implements ShouldQueue
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
    public $timeout = 60;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Skip the job if the user started a new export (which deletes this one) before it ran.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BackupExportModel $backupExport
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->backupExport->update(['status' => 'in_progress']);

        $portfolioIds = $this->backupExport->user->portfolios()->pluck('portfolios.id');

        if ($portfolioIds->isEmpty()) {

            AssembleBackupExportJob::dispatch($this->backupExport);

            return;
        }

        // the performance figures are slow to calculate, so each portfolio gets its own job
        // and the workbook is built once they have all finished
        Bus::chain([
            Bus::batch($portfolioIds->map(fn ($portfolioId) => new CalculateDailyPerformanceJob($this->backupExport, $portfolioId))->all())
                ->name('Backup export '.$this->backupExport->id),
            new AssembleBackupExportJob($this->backupExport),
        ])->dispatch();
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->backupExport->markAsFailed();
    }
}
