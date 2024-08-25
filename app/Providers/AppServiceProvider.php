<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
        $market_data = config(
            "market_data." . 
            config('market_data.default', 'yahoo')
        );

        $this->app->bind(
            \App\Interfaces\MarketData\MarketDataInterface::class,
            $market_data
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
