<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('slot', ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe']);
            $table->foreignUuid('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'slot']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_slots');
    }
};
