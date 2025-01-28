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
        Schema::table('holdings', function (Blueprint $table) {
            $table->boolean('reinvest_dividends')->default(false)->after('quantity');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('reinvested_dividend')->default(false)->after('split');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('reinvest_dividends');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('reinvested_dividend');
        });
    }
};
