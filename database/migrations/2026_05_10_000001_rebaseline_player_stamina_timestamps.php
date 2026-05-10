<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('player_stats')->update([
            'stamina_last_updated_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
