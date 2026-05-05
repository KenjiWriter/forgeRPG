<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('experience')->default(0)->after('remember_token');
            $table->unsignedSmallInteger('level')->default(1)->after('experience');
            $table->foreignId('current_island_id')->nullable()->constrained('islands')->nullOnDelete()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_island_id']);
            $table->dropColumn(['experience', 'level', 'current_island_id']);
        });
    }
};
