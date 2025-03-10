<?php

declare(strict_types=1);

use App\Models\Portfolio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHoldingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('holdings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Portfolio::class, 'portfolio_id')->constrained()->onDelete('cascade');
            $table->string('symbol', 25)->when(config('database.default') != 'sqlite', fn ($ctx) => $ctx->fulltext());
            $table->float('quantity', 12, 4);
            $table->float('average_cost_basis', 12, 4)->default(0);
            $table->float('total_cost_basis', 12, 4)->default(0);
            $table->float('realized_gain_dollars', 12, 4)->default(0);
            $table->float('dividends_earned', 12, 4)->default(0);
            $table->timestamp('splits_synced_at')->nullable();
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
        Schema::dropIfExists('holdings');
    }
}
