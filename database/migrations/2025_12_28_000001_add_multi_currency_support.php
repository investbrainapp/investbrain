<?php

declare(strict_types=1);

use Database\Seeders\CurrencySeeder;
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
        // todo: need to create default settings!
        Schema::table('users', function (Blueprint $table) {
            $table->json('options')->nullable()->after('profile_photo_path');
        });

        Schema::table('market_data', function (Blueprint $table) {
            $table->string('currency', 3)->after('market_value');
        });

        // todo: need to copy over values to these cells!
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('cost_basis_base', 12, 4)->after('sale_price');
            $table->float('sale_price_base', 12, 4)->nullable()->after('cost_basis_base');
        });

        // todo: need to copy over values to these cells!
        Schema::table('dividends', function (Blueprint $table) {
            $table->float('dividend_amount_base', 12, 4)->after('dividend_amount');
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->string('currency', 3)->primary(); // ISO 4217
            $table->string('label');
            $table->float('rate', 12, 4);
            $table->boolean('is_alias')->nullable();
            $table->timestamps();
        });

        if (config('app.env') != 'testing') {

            Artisan::call('db:seed', [
                '--class' => CurrencySeeder::class,
                '--force' => true,
            ]);

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
