<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Storage;

class BackupExport extends Model
{
    use HasUuids;
    use Prunable;

    public const DISK = 'local';

    /**
     * Number of hours a finished export can be downloaded for.
     */
    public const RETENTION_HOURS = 24;

    protected $table = 'backup_export_jobs';

    protected $fillable = [
        'user_id',
        'status', // pending, in_progress, success, failed
        'path', // where the finished workbook is stored
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($export) {

            $export->status = 'pending';
        });

        static::deleting(function ($export) {

            if ($export->path) {
                Storage::disk(static::DISK)->delete($export->path);
            }
        });
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
     * The user's latest export that is still running or can still be downloaded.
     */
    public static function latestFor(int|string $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(static::RETENTION_HOURS))
            ->latest()
            ->first();
    }

    public function filename(): string
    {
        return $this->created_at->format('Y_m_d').'_investbrain_backup.xlsx';
    }

    /**
     * Expired exports (and their files) are removed by the scheduled model:prune command.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subHours(static::RETENTION_HOURS));
    }
}
