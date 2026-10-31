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
        Schema::create('market_data_provider_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('method', 50);
            $table->timestamp('hour');
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('fallback_count')->default(0);
            $table->unsignedBigInteger('total_duration_ms')->default(0);

            $table->unique(['provider', 'method', 'hour']);
            $table->index('hour');
        });

        Schema::create('market_data_provider_failures', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('method', 50);
            $table->string('symbol', 25)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['provider', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_data_provider_metrics');
        Schema::dropIfExists('market_data_provider_failures');
    }
};
