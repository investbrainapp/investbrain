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
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->nullableUuidMorphs('chatable');
            $table->unique(['user_id', 'chatable_type', 'chatable_id'], 'chat_with_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropUnique('chat_with_unique');
            $table->dropMorphs('chatable');
        });
    }
};
