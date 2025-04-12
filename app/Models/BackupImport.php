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
        'message', // Import starting, Import is in progress, Importing portfolios, Importing transactions, Importing daily changes, Import completed successfully
        'has_errors',
        'completed_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($import) {

            $import->status = 'pending';
            $import->message = __('Import starting...');
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
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
