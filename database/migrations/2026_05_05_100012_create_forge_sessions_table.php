<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forge_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('ore_inputs');
            $table->foreignId('forge_rune_id')->nullable()->constrained('runes')->nullOnDelete();
            $table->unsignedTinyInteger('smelting_score')->default(0);
            $table->unsignedTinyInteger('smithing_score')->default(0);
            $table->unsignedTinyInteger('quench_score')->default(0);
            $table->unsignedTinyInteger('combined_score')->default(0);
            $table->unsignedTinyInteger('forge_grade')->default(0);
            $table->foreignId('result_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forge_sessions');
    }
};
