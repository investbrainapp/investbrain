<?php

namespace App\Providers;

use Illuminate\Support\Arr;
use Laravel\Jetstream\Features;
use App\Actions\Jetstream\DeleteUser;
use Illuminate\Support\Facades\Config;
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

        if ( config('investbrain.self_hosted', false) ) {

            Config::set(
                'jetstream.features',
                array_keys(Arr::except(array_values(config('jetstream.features')), Features::termsAndPrivacyPolicy()))
            );
        }
    }

    /**
     * Configure the permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::permissions([
            'create',
            'read',
            'update',
            'delete',
        ]);
    }
}
