<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Passport needs a personal access client to issue the API tokens users create
     * from the API Tokens screen.
     */
    public function up(): void
    {
        $provider = config('auth.guards.api.provider');

        $exists = Passport::client()->newQuery()
            ->where('revoked', false)
            ->where('provider', $provider)
            ->get()
            ->contains(fn ($client) => $client->hasGrantType('personal_access'));

        if (! $exists) {
            app(ClientRepository::class)->createPersonalAccessGrantClient(
                config('app.name').' Personal Access Client',
                $provider,
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Passport::client()->newQuery()
            ->where('name', config('app.name').' Personal Access Client')
            ->delete();
    }
};
