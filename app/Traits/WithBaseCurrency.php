<?php

declare(strict_types=1);

namespace App\Traits;

use App\Casts\BaseCurrency;
use Illuminate\Support\Str;

trait WithBaseCurrency
{
    // Adding a boot method to listen to the saving event
    protected static function booted()
    {
        parent::boot();

        static::saving(function ($model) {
            foreach ($model->casts as $key => $value) {
                if ($value === BaseCurrency::class) {

                    $model[$key] = $model[Str::beforeLast($key, '_base')];
                }
            }
        });
    }
}
