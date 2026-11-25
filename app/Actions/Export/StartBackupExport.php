<?php

declare(strict_types=1);

namespace App\Actions\Export;

use App\Jobs\AssembleBackupExportJob;
use App\Jobs\CalculateDailyPerformanceJob;
use App\Models\BackupExport;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;

class StartBackupExport
{
    /**
     * Exports each user can start per minute.
     */
    public const EXPORTS_PER_MINUTE = 3;

    /**
     * Start building a backup of everything the user can access. Shared by the
     * Import / Export page and the API so both follow the same rules.
     *
     * An export that is still being prepared is returned instead of starting another.
     *
     * @throws ThrottleRequestsException
     */
    public function handle(User $user): BackupExport
    {
        // e.g. a second tab or a double click while an export is being prepared
        $latestExport = BackupExport::latestFor($user->id);

        if ($latestExport && ! $latestExport->isFinished()) {
            return $latestExport;
        }

        $rateLimiterKey = 'export:'.$user->id;

        if (! RateLimiter::attempt($rateLimiterKey, self::EXPORTS_PER_MINUTE, fn () => null)) {
            throw new ThrottleRequestsException(
                __('Hang on! You\'re doing that too much.'),
                headers: ['Retry-After' => RateLimiter::availableIn($rateLimiterKey)],
            );
        }

        // only the latest export is kept
        BackupExport::where('user_id', $user->id)->get()->each->delete();

        $backupExport = BackupExport::create(['user_id' => $user->id]);

        // the performance figures are slow to calculate, so each portfolio gets its own job
        // and the workbook is built once they have all finished
        $performanceJobs = $user->portfolios()->pluck('portfolios.id')
            ->map(fn ($portfolioId) => new CalculateDailyPerformanceJob($backupExport, $portfolioId));

        $jobs = [new AssembleBackupExportJob($backupExport)];

        if ($performanceJobs->isNotEmpty()) {
            array_unshift($jobs, Bus::batch($performanceJobs->all())->name('Backup export '.$backupExport->id));
        }

        Bus::chain($jobs)->dispatch();

        return $backupExport->refresh();
    }
}
