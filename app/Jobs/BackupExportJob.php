<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\BackupExport;
use App\Models\BackupExport as BackupExportModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Builds the backup workbook in the background so large accounts don't run into the web
 * request's time and memory limits.
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
    public $timeout = 900;

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

        // the export sheets are scoped to the authenticated user
        Auth::setUser($this->backupExport->user);

        try {
            $path = 'exports/'.$this->backupExport->user_id.'/'.$this->backupExport->id.'.xlsx';

            Excel::store(new BackupExport, $path, BackupExportModel::DISK);

            $this->backupExport->update([
                'status' => 'success',
                'path' => $path,
                'completed_at' => now(),
            ]);
        } finally {
            // queue workers are long lived, so don't leak the user into the next job
            Auth::forgetUser();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->backupExport->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }
}
