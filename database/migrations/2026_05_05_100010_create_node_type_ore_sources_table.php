<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_type_ore_sources', function (Blueprint $table) {
            $table->foreignId('node_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ore_type_id')->constrained()->cascadeOnDelete();

            $table->primary(['node_type_id', 'ore_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_type_ore_sources');
    }
};
