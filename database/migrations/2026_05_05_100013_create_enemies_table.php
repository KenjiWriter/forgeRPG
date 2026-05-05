<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enemies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('island_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('hp');
            $table->unsignedSmallInteger('attack');
            $table->unsignedSmallInteger('defense');
            $table->unsignedSmallInteger('attack_speed');
            $table->enum('elemental_affinity', ['fire', 'water', 'earth', 'void', 'neutral'])->default('neutral');
            $table->json('drop_table');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enemies');
    }
};
