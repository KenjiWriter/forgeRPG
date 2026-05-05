<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('hp')->default(100);
            $table->unsignedSmallInteger('attack')->default(10);
            $table->unsignedSmallInteger('defense')->default(5);
            $table->unsignedSmallInteger('mining_speed')->default(10);
            $table->unsignedSmallInteger('attack_speed')->default(10);
            $table->unsignedTinyInteger('dodge')->default(0);
            $table->unsignedTinyInteger('stamina')->default(100);
            $table->timestamp('stamina_last_updated_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
