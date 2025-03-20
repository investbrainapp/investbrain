<?php

declare(strict_types=1);

use App\Models\CurrencyRate;
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
            $table->json('options')->default(json_encode([
                'locale' => config('app.locale', 'en'),
                'display_currency' => config('investbrain.base_currency', 'USD'),
            ]))->after('profile_photo_path');
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
            // todo: make transacitons have a currency selector
            $table->float('cost_basis_base', 12, 4)->nullable()->after('sale_price');
            $table->float('sale_price_base', 12, 4)->nullable()->after('cost_basis_base');
        });
        DB::table('transactions')->update([
            'cost_basis_base' => DB::raw('cost_basis'),
            'sale_price_base' => DB::raw('sale_price'),
        ]);
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('cost_basis_base', 12, 4)->nullable(false)->change();
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
         * Add _base columns to holdings table
         */
        Schema::table('holdings', function (Blueprint $table) {
            $table->float('total_cost_basis_base', 12, 4)->nullable()->after('dividends_earned');
            $table->float('realized_gain_base', 12, 4)->nullable()->after('total_cost_basis_base');
            $table->float('dividends_earned_base', 12, 4)->nullable()->after('realized_gain_base');
        });
        DB::table('holdings')->update([
            'total_cost_basis_base' => DB::raw('total_cost_basis'),
            'realized_gain_base' => DB::raw('realized_gain_dollars'),
            'dividends_earned_base' => DB::raw('dividends_earned'),
        ]);
        Schema::table('holdings', function (Blueprint $table) {
            $table->float('total_cost_basis_base', 12, 4)->nullable(false)->change();
            $table->float('realized_gain_base', 12, 4)->nullable(false)->change();
            $table->float('dividends_earned_base', 12, 4)->nullable(false)->change();
        });

        /**
         * Creates currencies table
         */
        Schema::create('currencies', function (Blueprint $table) {
            $table->string('currency', 3)->primary(); // ISO 4217
            $table->string('label');
            $table->timestamps();
        });

        Artisan::call('db:seed', [
            '--class' => CurrencySeeder::class,
            '--force' => true,
        ]);

        /**
         * Creates currency rates table
         */
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->date('date');
            $table->string('currency', 3); 
            $table->float('rate', 12, 4);
            $table->timestamps();

            $table->primary(['date', 'currency']);
        });

        if (config('app.env') != 'testing') {

            CurrencyRate::refreshCurrencyData();

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
            $table->dropColumn('market_value_base');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('cost_basis_base');
            $table->dropColumn('sale_price_base');
        });

        Schema::table('dividends', function (Blueprint $table) {
            $table->dropColumn('dividend_amount_base');
        });

        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('total_cost_basis_base');
            $table->dropColumn('realized_gain_base');
            $table->dropColumn('dividends_earned_base');
        });

        Schema::dropIfExists('currencies');

        Schema::dropIfExists('currency_rates');
    }
};
