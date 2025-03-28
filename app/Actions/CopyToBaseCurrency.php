<?php

declare(strict_types=1);

namespace App\Actions;

use App\Casts\BaseCurrency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CopyToBaseCurrency
{
    public function __invoke(Model $model, callable $next)
    {
        foreach ($model->getCasts() as $key => $value) {
            if ($value === BaseCurrency::class) {

                $model[$key] = $model[Str::beforeLast($key, '_base')];
            }
        }

        return $next($model);
    }
}
