<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SyncDailyChangesJob;
use Illuminate\Database\Eloquent\Model;

class EnsureDailyChangeIsSynced
{
    public function __invoke(Model $model, callable $next)
    {
        if (config('app.env') != 'testing') {

            if (
                $model->date->lessThan(now())
                && ($model->date->lessThan($model->portfolio->daily_change()->min('date') ?? now())
                    || $model->date->lessThan($model->portfolio->transactions()->where('id', '!=', $model->id)->max('date') ?? now())
                )
            ) {
                // queued, one at a time per portfolio, and a newer one replaces any still queued or running
                SyncDailyChangesJob::dispatchFor($model->portfolio);
            }
        }

        return $next($model);
    }
}
