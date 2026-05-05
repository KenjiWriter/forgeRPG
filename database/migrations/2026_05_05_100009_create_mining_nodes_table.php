<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mining_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('island_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_hp');
            $table->unsignedInteger('current_hp');
            $table->timestamp('respawns_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mining_nodes');
    }
};
