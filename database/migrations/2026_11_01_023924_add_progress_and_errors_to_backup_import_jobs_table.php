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
        Schema::table('backup_import_jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('message');
            $table->unsignedInteger('error_count')->default(0)->after('has_errors');
            $table->json('errors')->nullable()->after('error_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_import_jobs', function (Blueprint $table) {
            $table->dropColumn(['progress', 'error_count', 'errors']);
        });
    }
};
