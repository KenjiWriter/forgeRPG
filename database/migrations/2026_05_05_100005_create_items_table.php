<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('slot_type', ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe']);
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
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
