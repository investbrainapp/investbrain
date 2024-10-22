<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('portfolio_users', function (Blueprint $table) {
            
            $table->renameColumn('write', 'full_access');
            $table->datetime('invite_accepted_at')->nullable();
        });

       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
        
    }
};