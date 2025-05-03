<?php

declare(strict_types=1);

use App\Models\CurrencyRate;
use App\Models\Holding;
use App\Models\Transaction;
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
         * Creates currencies table
         */
        Schema::create('currencies', function (Blueprint $table) {
            $table->string('currency', 3)->primary(); // ISO 4217
            $table->string('label');
            $table->timestamps();
        });

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

            Artisan::call('db:seed', [
                '--class' => CurrencySeeder::class,
                '--force' => true,
            ]);

            CurrencyRate::timeSeriesRates(
                Holding::all()->groupBy('market_data.currency')->keys()->toArray(),
                Transaction::min('date')
            );

            CurrencyRate::refreshCurrencyData();

            Artisan::call('db:seed', [
                '--class' => MarketDataSeeder::class,
                '--force' => true,
            ]);
        }

        /**
         * Cleanup daily change table
         */
        if (Schema::hasColumn('daily_change', 'total_cost_basis')) {
            Schema::table('daily_change', function (Blueprint $table) {
                $table->dropColumn('total_cost_basis');
            });
        }
        if (Schema::hasColumn('daily_change', 'total_gain')) {
            Schema::table('daily_change', function (Blueprint $table) {
                $table->dropColumn('total_gain');
            });
        }
        if (Schema::hasColumn('daily_change', 'total_dividends_earned')) {
            Schema::table('daily_change', function (Blueprint $table) {
                $table->dropColumn('total_dividends_earned');
            });
        }
        if (Schema::hasColumn('daily_change', 'realized_gains')) {
            Schema::table('daily_change', function (Blueprint $table) {
                $table->dropColumn('realized_gains');
            });
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

        Schema::dropIfExists('currencies');

        Schema::dropIfExists('currency_rates');
    }
};
