<?php

declare(strict_types=1);

use App\Models\Portfolio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->string('symbol', 15);
            $table->foreignIdFor(Portfolio::class, 'portfolio_id')->constrained()->onDelete('cascade');
            $table->string('transaction_type', 15);
            $table->float('quantity', 12, 4);
            $table->float('cost_basis', 12, 4);
            $table->float('sale_price', 12, 4)->nullable();
            $table->boolean('split')->default(false);
            $table->date('date');
            $table->timestamps();

            $table->foreign('symbol')->references('symbol')->on('market_data');
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
