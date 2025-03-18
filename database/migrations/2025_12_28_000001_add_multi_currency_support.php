<?php

declare(strict_types=1);

use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\MarketDataSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->json('options')->default([
                'locale' => config('app.locale', 'en'),
                'display_currency' => config('investbrain.base_currency', 'USD'),
            ])->after('profile_photo_path');
        });

        /**
         * Add _base and currency column to market_data table
         */
        Schema::table('market_data', function (Blueprint $table) {
            $table->float('market_value_base', 12, 4)->nullable()->after('market_value');
            $table->string('currency', 3)->default(config('investbrain.base_currency'))->after('market_value');
        });
        DB::table('market_data')->update([
            'market_value_base' => DB::raw('market_value'),
        ]);

        /**
         * Add _base columns to transactions table
         */
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('cost_basis_base', 12, 4)->nullable()->after('sale_price');
            $table->float('sale_price_base', 12, 4)->nullable()->after('cost_basis_base');
            // $table->json('current_rates')->default([])->after('sale_price_base');
        });
        DB::table('transactions')->update([
            'cost_basis_base' => DB::raw('cost_basis'),
            'sale_price_base' => DB::raw('sale_price'),
        ]);
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('cost_basis_base', 12, 4)->nullable(false)->change();
        });

        // /**
        //  * Add rate column to holdings table
        //  */
        // Schema::table('holdings', function (Blueprint $table) {
        //     $table->json('cost_basis_avg_rates')->default([])->after('dividends_earned');
        //     $table->json('realized_gain_avg_rates')->default([])->after('cost_basis_avg_rates');
        //     $table->json('dividends_avg_rates')->default([])->after('realized_gain_avg_rates');
        // });

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

        Artisan::call('db:seed', [
            '--class' => CurrencySeeder::class,
            '--force' => true,
        ]);

        if (config('app.env') != 'testing') {

            Currency::refreshCurrencyData();

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
            // $table->dropColumn('current_rates');
        });

        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('cost_basis_rate');
            $table->dropColumn('realized_gain_rate');
            $table->dropColumn('dividends_rate');
            // $table->dropColumn('cost_basis_avg_rates');
            // $table->dropColumn('realized_gain_avg_rates');
            // $table->dropColumn('dividends_avg_rates');
        });

        Schema::table('dividends', function (Blueprint $table) {
            $table->dropColumn('dividend_amount_base');
        });

        Schema::dropIfExists('currencies');
    }
};
