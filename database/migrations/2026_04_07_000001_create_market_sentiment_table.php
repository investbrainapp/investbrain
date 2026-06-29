<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('market_sentiment', function (Blueprint $table) {
            $table->string('symbol', 25)->primary();
            $table->float('average_buzz', 8, 2)->nullable();
            $table->float('average_bullish_pct', 8, 2)->nullable();
            $table->unsignedTinyInteger('coverage')->default(0);
            $table->string('source_alignment', 25)->nullable();

            $table->float('reddit_buzz', 8, 2)->nullable();
            $table->unsignedTinyInteger('reddit_bullish_pct')->nullable();
            $table->unsignedInteger('reddit_mentions')->nullable();

            $table->float('x_buzz', 8, 2)->nullable();
            $table->unsignedTinyInteger('x_bullish_pct')->nullable();
            $table->unsignedInteger('x_mentions')->nullable();

            $table->float('news_buzz', 8, 2)->nullable();
            $table->unsignedTinyInteger('news_bullish_pct')->nullable();
            $table->unsignedInteger('news_mentions')->nullable();

            $table->float('polymarket_buzz', 8, 2)->nullable();
            $table->unsignedTinyInteger('polymarket_bullish_pct')->nullable();
            $table->unsignedInteger('polymarket_trade_count')->nullable();

            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_sentiment');
    }
};
