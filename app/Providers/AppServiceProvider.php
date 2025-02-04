<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Interfaces\MarketData\MarketDataInterface::class,
            \App\Interfaces\MarketData\FallbackInterface::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Arr::macro('skipEmptyValues', function (array $array) {

            return Arr::mapWithKeys($array, function (mixed $value, mixed $key) {
                $result = [];
                if (! empty($value)) {
                    $result[] = [$key => $value];
                }

                return $result;
            });
        });
    }
}
