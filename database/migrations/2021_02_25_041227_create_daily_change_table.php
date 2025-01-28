<?php

use App\Models\Portfolio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDailyChangeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('daily_change', function (Blueprint $table) {
            $table->date('date');
            $table->foreignIdFor(Portfolio::class, 'portfolio_id')->constrained()->onDelete('cascade');
            $table->float('total_market_value', 12, 4)->nullable();
            $table->float('total_cost_basis', 12, 4)->nullable();
            $table->float('total_gain', 12, 4)->nullable();
            $table->float('total_dividends_earned', 12, 4)->nullable();
            $table->float('realized_gains', 12, 4)->nullable();
            $table->text('annotation')->nullable();

            $table->primary(['date', 'portfolio_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('daily_change');
    }
}
