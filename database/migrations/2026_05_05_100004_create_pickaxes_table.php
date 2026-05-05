<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickaxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('power');
            $table->unsignedTinyInteger('luck_boost');
            $table->decimal('speed_modifier', 4, 2);
            $table->unsignedTinyInteger('slots');
            $table->foreignId('requires_island_id')->nullable()->constrained('islands')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickaxes');
    }
};
