<?php

namespace App\Jobs;

use Throwable;
use App\Models\User;
use App\Models\BackupImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\ImportSucceededNotification;
use App\Notifications\ImportFailedNotification;
use App\Imports\BackupImport as BackupImportExcel;

class BackupImportJob implements ShouldQueue
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
    public $timeout = 300;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    public User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BackupImport $backupImport
    ) { 
        $this->user = User::find($this->backupImport->user_id);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {                
        Excel::import(new BackupImportExcel($this->backupImport), $this->backupImport->path, config('livewire.temporary_file_upload.disk', null));

        $this->user->notify(new ImportSucceededNotification);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $e): void
    {
        $this->backupImport->update([
            'status' => 'failed',
            'message' => 'Error: '. substr($e->getMessage(), 0, 220),
            'has_errors' => true,
            'completed_at' => now()
        ]);

        $this->user->notify(new ImportFailedNotification($e->getMessage()));
    }
}
