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
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        Schema::create('connected_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(User::class, 'user_id')->constrained()->onDelete('cascade');
            $table->string('provider');
            $table->string('provider_id');
            $table->string('token', 1000);
            $table->string('secret')->nullable(); // OAuth1
            $table->string('refresh_token', 1000)->nullable(); // OAuth2
            $table->dateTime('expires_at')->nullable(); // OAuth2
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['provider', 'provider_id']);
        });

        Schema::create('connected_account_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('provider');
            $table->string('provider_id');
            $table->json('connected_account');
            $table->timestamps();
            $table->timestamp('verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
        Schema::dropIfExists('connected_account_verifications');
        
        Schema::dropIfExists('connected_accounts');
    }
};