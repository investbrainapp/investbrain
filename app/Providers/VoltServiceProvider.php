<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class VoltServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Volt::mount([
            // config('livewire.view_path', resource_path('views/livewire')),
            resource_path('views/components'),
            resource_path('views/profile'),
            resource_path('views/api'),
            resource_path('views/holding'),
            resource_path('views/transaction'),
            resource_path('views/portfolio'),
            resource_path('views/import-export'),
            resource_path('views/auth'),
        ]);
    }
}
