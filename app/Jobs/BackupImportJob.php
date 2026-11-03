<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Imports\BackupImport as BackupImportExcel;
use App\Imports\ImportValidationException;
use App\Models\BackupImport;
use App\Notifications\ImportFailedNotification;
use App\Notifications\ImportSucceededNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class BackupImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Problems kept on the import record (and shown in the UI). The total is kept separately.
     */
    public const STORED_ERROR_LIMIT = 100;

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
     * Create a new job instance.
     */
    public function __construct(
        public BackupImport $backupImport
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->backupImport->reportProgress(__('Reading your file...'), 5);

        $import = new BackupImportExcel($this->backupImport);

        try {
            Excel::import($import, $this->backupImport->path, config('livewire.temporary_file_upload.disk', null));

            $summary = $import->process();

        } catch (ImportValidationException $e) {

            $this->markAsFailed($e->getMessage(), $e->errors);

            $this->backupImport->user->notify(new ImportFailedNotification($e->getMessage(), array_slice($e->errors, 0, 10), count($e->errors)));

            return;
        }

        $this->backupImport->update([
            'status' => 'success',
            'message' => __('Import complete! Portfolios: :portfolios, transactions: :transactions.', $summary),
            'progress' => 100,
            'completed_at' => now(),
        ]);

        $this->backupImport->user->notify(new ImportSucceededNotification);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $e): void
    {
        $this->markAsFailed(__('Something went wrong while importing your file. Nothing was imported.'), [[
            'sheet' => null,
            'row' => null,
            'column' => null,
            'value' => null,
            'message' => substr((string) $e?->getMessage(), 0, 500),
        ]]);

        $this->backupImport->user->notify(new ImportFailedNotification((string) $e?->getMessage()));
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    protected function markAsFailed(string $message, array $errors): void
    {
        $this->backupImport->update([
            'status' => 'failed',
            'message' => $message,
            'progress' => 100,
            'has_errors' => true,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, self::STORED_ERROR_LIMIT),
            'completed_at' => now(),
        ]);
    }
}
