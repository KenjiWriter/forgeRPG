<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop dependent tables first (cascade from items)
        if (Schema::hasTable('equipment_slots')) {
            Schema::drop('equipment_slots');
        }

        if (Schema::hasTable('forge_sessions')) {
            Schema::drop('forge_sessions');
        }

        if (Schema::hasTable('items')) {
            Schema::drop('items');
        }

        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->enum('target_slot', ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe']);
            $table->unsignedTinyInteger('forge_grade')->default(1);
            $table->string('forge_signature', 64)->nullable();
            $table->unsignedSmallInteger('hp_bonus')->default(0);
            $table->unsignedSmallInteger('attack_bonus')->default(0);
            $table->unsignedSmallInteger('defense_bonus')->default(0);
            $table->unsignedSmallInteger('mining_speed_bonus')->default(0);
            $table->unsignedSmallInteger('mining_dmg_bonus')->default(0);
            $table->unsignedTinyInteger('luck_bonus')->default(0);
            $table->unsignedSmallInteger('attack_speed_bonus')->default(0);
            $table->unsignedTinyInteger('dodge_bonus')->default(0);
            $table->enum('elemental_affinity', ['fire', 'water', 'earth', 'void', 'neutral'])->default('neutral');
            $table->json('base_stats')->nullable();
            $table->json('final_stats')->nullable();
            $table->boolean('equipped')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('player_id');
            $table->index('forge_signature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
