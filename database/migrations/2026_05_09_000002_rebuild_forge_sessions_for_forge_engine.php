<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forge_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
            $table->enum('target_slot', ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe']);
            $table->json('ore_inputs');
            $table->foreignId('forge_rune_id')->nullable()->constrained('runes')->nullOnDelete();
            $table->unsignedTinyInteger('smelting_score')->default(0);
            $table->unsignedTinyInteger('smithing_score')->default(0);
            $table->unsignedTinyInteger('quench_score')->default(0);
            $table->unsignedTinyInteger('combined_score')->default(0);
            $table->unsignedTinyInteger('forge_grade')->default(0);
            $table->foreignUuid('result_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('status')->default('in_progress'); // in_progress, completed, forfeit
            $table->timestamp('created_at')->nullable();

            $table->index('player_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forge_sessions');
    }
};
