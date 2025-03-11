<?php

declare(strict_types=1);

use Database\Seeders\CurrencySeeder;
use Database\Seeders\MarketDataSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Add options column to users table
         */
        Schema::table('users', function (Blueprint $table) {
            // todo: need to create default settings!
            $table->json('options')->nullable()->after('profile_photo_path');
        });

        /**
         * Add currency column to market_data table
         */
        Schema::table('market_data', function (Blueprint $table) {
            $table->string('currency', 3)->default(config('investbrain.base_currency'))->after('market_value');
        });

        /**
         * Add _base columns to transactions table
         */
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('cost_basis_base', 12, 4)->nullable()->after('sale_price');
            $table->float('sale_price_base', 12, 4)->nullable()->after('cost_basis_base');
        });
        DB::table('transactions')->update([
            'cost_basis_base' => DB::raw('cost_basis'),
            'sale_price_base' => DB::raw('sale_price'),
        ]);
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('cost_basis_base', 12, 4)->nullable(false)->after('sale_price')->change();
        });

        /**
         * Add _base columns to dividends table
         */
        Schema::table('dividends', function (Blueprint $table) {
            $table->float('dividend_amount_base', 12, 4)->nullable()->after('dividend_amount');
        });
        DB::table('dividends')->update([
            'dividend_amount_base' => DB::raw('dividend_amount'),
        ]);
        Schema::table('dividends', function (Blueprint $table) {
            $table->float('dividend_amount_base', 12, 4)->nullable(false)->change();
        });

        /**
         * Creates currencies table
         */
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
            $table->dropColumn('options');
        });

        Schema::table('market_data', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('cost_basis_base');
            $table->dropColumn('sale_price_base');
        });

        Schema::table('dividends', function (Blueprint $table) {
            $table->dropColumn('dividend_amount_base');
        });

        Schema::dropIfExists('currencies');
    }
};
