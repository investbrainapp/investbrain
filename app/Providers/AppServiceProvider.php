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
        if (!in_array(
            $interface = config('investbrain.default', 'yahoo'), 
            array_keys(config('investbrain.interfaces', []))
        )) {
            throw new \Exception("Error: '$interface' is not a valid market data interface.");
        }
        
        $this->app->bind(
            \App\Interfaces\MarketData\MarketDataInterface::class,
            config("investbrain.interfaces.$interface")
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
