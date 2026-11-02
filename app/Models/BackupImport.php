<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\BackupImportJob;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BackupImport extends Model
{
    use HasUuids;

    protected $table = 'backup_import_jobs';

    protected $fillable = [
        'user_id',
        'path',
        'status', // pending, in_progress, success, failed
        'message', // human readable description of the current step or outcome
        'progress', // 0 - 100
        'has_errors',
        'error_count',
        'errors', // list of problems found in the uploaded file
        'completed_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($import) {

            $import->status = 'pending';
            $import->message = __('Import starting...');
            $import->progress = 0;
        });

        static::created(function ($import) {

            BackupImportJob::dispatch($import);
        });
    }

    protected $hidden = [];

    protected $appends = [];

    protected function casts(): array
    {
        return [
            'has_errors' => 'boolean',
            'error_count' => 'integer',
            'errors' => 'array',
            'progress' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['success', 'failed']);
    }

    /**
     * Record progress so the polling UI can show what the import is doing.
     */
    public function reportProgress(string $message, int $progress): void
    {
        $this->update([
            'status' => 'in_progress',
            'message' => $message,
            'progress' => min(100, max(0, $progress)),
        ]);
    }
}
