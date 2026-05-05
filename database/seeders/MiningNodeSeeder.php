<?php

namespace Database\Seeders;

use App\Models\Island;
use App\Services\MiningService;
use Illuminate\Database\Seeder;

class MiningNodeSeeder extends Seeder
{
    public function run(): void
    {
        $miningService = app(MiningService::class);
        $islands = Island::with('nodeTypes')->get();
        $total = 0;

        foreach ($islands as $island) {
            $spawned = $miningService->spawnNodesForIsland($island);
            $total += $spawned;
            $this->command->line("  {$island->name}: +{$spawned} node(s)");
        }

        $this->command->info("Mining nodes seeded: {$total} total.");
    }
}
