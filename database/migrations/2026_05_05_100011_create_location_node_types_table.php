<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_node_types', function (Blueprint $table) {
            $table->foreignId('island_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_type_id')->constrained()->cascadeOnDelete();

            $table->primary(['island_id', 'node_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_node_types');
    }
};
