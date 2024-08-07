<?php

use App\Models\User;
use App\Models\Portfolio;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('portfolio_user', function (Blueprint $table) {
            $table->foreignIdFor(Portfolio::class, 'portfolio_id')->onDelete('cascade');
            $table->foreignIdFor(User::class, 'user_id')->onDelete('cascade');
            $table->boolean('owner')->default(false);
            $table->boolean('read')->default(false);
            $table->boolean('write')->default(false);
            $table->primary(['portfolio_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('portfolio_user');
    }
};