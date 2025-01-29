<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Jetstream\DeleteUser;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $this->configurePermissions();

        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions([
            // 'portfolio:read',
            // 'portfolio:write',
            // 'holding:read',
            // 'holding:write',
            // 'transaction:read',
            // 'transaction:write',
        ]);

        Jetstream::permissions([
            // 'Read Portfolios' => 'portfolio:read',
            // 'Create Portfolios' => 'portfolio:write',
            // 'Read Holdings' => 'holding:read',
            // 'Update Holdings' => 'holding:write',
            // 'Read Transactions' => 'transaction:read',
            // 'Create Transactions' => 'transaction:write',
        ]);
    }
}
