<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NODE_HP_SCALING_FACTOR = 0.7;

    public function up(): void
    {
        $now = now();

        DB::table('mining_nodes')
            ->orderBy('id')
            ->select(['id', 'max_hp', 'current_hp'])
            ->chunkById(200, function ($nodes) use ($now): void {
                foreach ($nodes as $node) {
                    $scaledMaxHp = max(1, (int) round(((int) $node->max_hp) * self::NODE_HP_SCALING_FACTOR));
                    $scaledCurrentHp = (int) round(((int) $node->current_hp) * self::NODE_HP_SCALING_FACTOR);

                    DB::table('mining_nodes')
                        ->where('id', $node->id)
                        ->update([
                            'max_hp' => $scaledMaxHp,
                            'current_hp' => max(0, min($scaledCurrentHp, $scaledMaxHp)),
                            'updated_at' => $now,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
