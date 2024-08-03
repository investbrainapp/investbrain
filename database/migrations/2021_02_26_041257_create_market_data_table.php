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
            $table->string('symbol')->primary();
            $table->string('name');
            $table->float('market_value', 12, 4);
            $table->float('fifty_two_week_low', 12, 4);
            $table->float('fifty_two_week_high', 12, 4);
            $table->timestamp('splits_synced_to_holdings_at')->nullable();
            $table->timestamp('dividend_date')->nullable();
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
