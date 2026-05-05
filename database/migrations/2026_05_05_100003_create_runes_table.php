<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['forge', 'skill']);
            $table->json('effect');
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runes');
    }
};
