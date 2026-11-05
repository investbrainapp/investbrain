<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The performance, dividend and cost basis queries look transactions up by portfolio and symbol,
     * and dividends and daily changes by symbol or portfolio first. The existing keys lead with the
     * date, and foreign keys aren't indexed by PostgreSQL, so these lookups scanned whole tables.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['portfolio_id', 'symbol', 'date']);
            $table->index(['symbol', 'date']);
        });

        Schema::table('holdings', function (Blueprint $table) {
            $table->index(['portfolio_id', 'symbol']);
        });

        Schema::table('dividends', function (Blueprint $table) {
            $table->index(['symbol', 'date']);
        });

        Schema::table('daily_change', function (Blueprint $table) {
            $table->index(['portfolio_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['portfolio_id', 'symbol', 'date']);
            $table->dropIndex(['symbol', 'date']);
        });

        Schema::table('holdings', function (Blueprint $table) {
            $table->dropIndex(['portfolio_id', 'symbol']);
        });

        Schema::table('dividends', function (Blueprint $table) {
            $table->dropIndex(['symbol', 'date']);
        });

        Schema::table('daily_change', function (Blueprint $table) {
            $table->dropIndex(['portfolio_id', 'date']);
        });
    }
};
