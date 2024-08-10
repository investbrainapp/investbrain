<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->string('name');
            $table->float('market_value', 12, 4);
            $table->float('fifty_two_week_low', 12, 4);
            $table->float('fifty_two_week_high', 12, 4);
            $table->timestamp('last_dividend_date')->nullable();
            $table->float('last_dividend_amount', 12, 4);
            $table->timestamps();
        });
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
