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

    /**
     * Number of hours a finished export can be downloaded for.
     */
    public const RETENTION_HOURS = 24;

    protected $table = 'backup_export_jobs';

    protected $fillable = [
        'user_id',
        'status', // pending, success, failed
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
                Storage::disk()->delete($export->path);
            }

            Storage::disk()->deleteDirectory($export->performanceDirectory());
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

    /**
     * Where each portfolio's calculated performance figures are kept while the export is built.
     */
    public function performanceDirectory(): string
    {
        return 'exports/'.$this->user_id.'/'.$this->id.'-performance';
    }

    public function performancePath(string $portfolioId): string
    {
        return $this->performanceDirectory().'/'.$portfolioId.'.json';
    }

    /**
     * Where the finished workbook is stored.
     */
    public function workbookPath(): string
    {
        return 'exports/'.$this->user_id.'/'.$this->id.'.xlsx';
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        Storage::disk()->deleteDirectory($this->performanceDirectory());
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
