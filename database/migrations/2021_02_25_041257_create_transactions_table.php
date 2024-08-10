<?php

use App\Models\Portfolio;
use App\Models\MarketData;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignIdFor(MarketData::class, 'symbol');
            $table->foreignIdFor(Portfolio::class, 'portfolio_id')->constrained()->onDelete('cascade');
            $table->string('transaction_type', 15);
            $table->float('quantity', 12, 4);
            $table->float('cost_basis', 12, 4);
            $table->float('sale_price', 12, 4)->nullable();
            $table->boolean('split')->nullable();
            $table->date('date');
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
        Schema::dropIfExists('transactions');
    }
}
