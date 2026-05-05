<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_definitions', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->primary();
            $table->unsignedBigInteger('exp_required');
            $table->string('unlock_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_definitions');
    }
};
