<?php

use Database\Seeders\MarketDataSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMarketDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('market_data', function (Blueprint $table) {
            $table->string('symbol', 15)->primary();
            $table->string('name')->nullable();
            $table->float('market_value', 12, 4)->nullable();
            $table->float('fifty_two_week_low', 12, 4)->nullable();
            $table->float('fifty_two_week_high', 12, 4)->nullable();
            $table->timestamp('last_dividend_date')->nullable();
            $table->float('last_dividend_amount', 12, 4)->nullable();
            $table->float('dividend_yield', 12, 4)->nullable();
            $table->unsignedBigInteger('market_cap')->nullable();
            $table->float('trailing_pe', 12, 4)->nullable();
            $table->float('forward_pe', 12, 4)->nullable();
            $table->float('book_value', 12, 4)->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });

        Artisan::call('db:seed', [
            '--class' => MarketDataSeeder::class,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('market_data');
    }
}
