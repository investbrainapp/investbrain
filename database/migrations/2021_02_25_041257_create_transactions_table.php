<?php

use App\Models\Portfolio;
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
            $table->id();
            $table->string('symbol');
            $table->foreignIdFor(Portfolio::class, 'portfolio_id')->onDelete('cascade');
            $table->string('transaction_type');
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
