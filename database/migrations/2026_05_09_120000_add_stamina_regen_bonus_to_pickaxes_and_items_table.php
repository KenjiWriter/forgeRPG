<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickaxes', function (Blueprint $table) {
            $table->float('stamina_regen_bonus', precision: 24)->default(0)->after('luck_boost');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->float('stamina_regen_bonus', precision: 24)->default(0)->after('luck_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('pickaxes', function (Blueprint $table) {
            $table->dropColumn('stamina_regen_bonus');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('stamina_regen_bonus');
        });
    }
};
