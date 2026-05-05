<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ore_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('rarity', ['common', 'uncommon', 'rare', 'epic', 'legendary', 'mythical']);
            $table->unsignedInteger('base_chance');
            $table->decimal('multiplier', 4, 2);
            $table->unsignedInteger('price');
            $table->enum('elemental_affinity', ['fire', 'water', 'earth', 'void', 'neutral'])->default('neutral');
            $table->unsignedSmallInteger('base_attack')->default(0);
            $table->unsignedSmallInteger('base_defense')->default(0);
            $table->unsignedSmallInteger('base_hp')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ore_types');
    }
};
