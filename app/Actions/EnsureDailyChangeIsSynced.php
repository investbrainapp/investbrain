<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

use function Illuminate\Support\defer;

class EnsureDailyChangeIsSynced
{
    public function __invoke(Model $model, callable $next)
    {
        if (config('app.env') != 'testing') {

            $cacheKey = 'daily_change_synced'.$model->portfolio_id;

            if (
                ! Cache::has($cacheKey)
                && $model->date->lessThan(now())
                && ($model->date->lessThan($model->portfolio->daily_change()->min('date') ?? now())
                    || $model->date->lessThan($model->portfolio->transactions()->where('id', '!=', $model->id)->max('date') ?? now())
                )
            ) {
                defer(fn () => $model->portfolio->syncDailyChanges());

                Cache::put($cacheKey, now(), now()->addMinutes(5));
            }
        }

        return $next($model);
    }
}
