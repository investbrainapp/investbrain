<?php

declare(strict_types=1);

use Database\Seeders\MarketDataSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('currency', 3)->after('profile_photo_path');
        });

        Schema::table('market_data', function (Blueprint $table) {
            $table->string('currency', 3)->after('market_value');
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->string('currency', 3)->primary(); // ISO 4217
            $table->string('label');
            $table->float('rate_to_usd', 12, 4);
        });

        if (config('app.env') != 'testing') {

            Artisan::call('db:seed', [
                '--class' => MarketDataSeeder::class,
                '--force' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('market_data', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::dropIfExists('currencies');
    }
};
