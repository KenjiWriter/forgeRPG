<?php

namespace App\Http\Controllers;

use App\Models\Island;
use App\Models\LevelDefinition;
use App\Models\MiningNode;
use App\Models\OreType;
use App\Models\PlayerStat;
use App\Services\MiningService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load(['stats', 'currentIsland', 'equipmentSlots.item']);

        /** @var PlayerStat $stats */
        $stats = $user->stats;

        $effectiveStamina = $this->computeStamina($stats);
        $now = now();

        $equippedPickaxe = $user->equipmentSlots
            ->firstWhere('slot', 'pickaxe')
            ?->item;

        $island = $user->currentIsland;

        if ($island === null) {
            $island = Island::where('min_level', 1)->orderBy('id')->first() ?? Island::orderBy('id')->first();

            if ($island) {
                $user->current_island_id = $island->id;
                $user->saveQuietly();
                $user->setRelation('currentIsland', $island);
            }
        }

        $node = $island
            ? MiningNode::where('island_id', $island->id)
                ->where('current_hp', '>', 0)
                ->whereNull('respawns_at')
                ->with('nodeType')
                ->oldest()
                ->first()
            : null;

        if ($node === null && $island !== null) {
            app(MiningService::class)->spawnNodesForIsland($island);

            $node = MiningNode::where('island_id', $island->id)
                ->where('current_hp', '>', 0)
                ->whereNull('respawns_at')
                ->with('nodeType')
                ->oldest()
                ->first();
        }

        $inventory = $user->inventory()
            ->where('holdable_type', OreType::class)
            ->with('holdable')
            ->get()
            ->map(fn ($slot) => [
                'id' => $slot->holdable->id,
                'name' => $slot->holdable->name,
                'quantity' => $slot->quantity,
            ]);

        $nextLevelDef = LevelDefinition::where('level', '>', $user->level)
            ->orderBy('level')
            ->first();

        return Inertia::render('Dashboard', [
            'player' => [
                'id' => $user->id,
                'name' => $user->name,
                'level' => $user->level,
                'experience' => $user->experience,
                'next_level_exp' => $nextLevelDef?->exp_required ?? ($user->experience + 100),
            ],
            'player_stats' => [
                'stamina' => $effectiveStamina,
                'stamina_last_updated_at' => $now->toIso8601String(),
                'hp' => $stats->hp,
            ],
            'island' => $island ? [
                'id' => $island->id,
                'name' => $island->name,
            ] : null,
            'current_node' => $node ? [
                'id' => $node->id,
                'max_hp' => $node->max_hp,
                'current_hp' => $node->current_hp,
                'is_respawning' => $node->isRespawning(),
                'respawns_at' => $node->respawns_at?->toIso8601String(),
                'node_type' => [
                    'slug' => $node->nodeType->slug,
                    'name' => $node->nodeType->name,
                    'tier' => $node->nodeType->tier,
                ],
            ] : null,
            'inventory' => $inventory,
            'equipped_pickaxe' => $equippedPickaxe ? [
                'id' => $equippedPickaxe->id,
                'name' => $equippedPickaxe->name,
                'mining_dmg_bonus' => $equippedPickaxe->mining_dmg_bonus,
                'luck_bonus' => $equippedPickaxe->luck_bonus,
            ] : null,
        ]);
    }

    private function computeStamina(PlayerStat $stats): float
    {
        if ($stats->stamina_last_updated_at === null) {
            return (float) $stats->stamina;
        }

        $elapsed = max(0, now()->timestamp - $stats->stamina_last_updated_at->timestamp);

        return min(100.0, (float) $stats->stamina + ($elapsed * 3));
    }
}
