<?php

namespace App\Console\Commands;

use App\Models\Island;
use App\Services\MiningService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('game:spawn-nodes {--min=1 : Minimum active nodes per node type per island}')]
#[Description('Ensure every island has at least the specified number of active nodes per configured node type')]
class SpawnNodesCommand extends Command
{
    public function __construct(private readonly MiningService $miningService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $minimum = (int) $this->option('min');
        $islands = Island::with('nodeTypes')->get();
        $totalSpawned = 0;

        $this->info("Spawning nodes (minimum {$minimum} per type per island)...");

        foreach ($islands as $island) {
            $spawned = $this->miningService->spawnNodesForIsland($island, $minimum);
            $totalSpawned += $spawned;

            if ($spawned > 0) {
                $this->line("  <fg=green>+{$spawned}</> node(s) spawned on <fg=cyan>{$island->name}</>");
            } else {
                $this->line("  <fg=gray>✓</> {$island->name} already has sufficient nodes");
            }
        }

        $this->newLine();
        $this->info("Done. Total nodes spawned: {$totalSpawned}");

        return self::SUCCESS;
    }
}
