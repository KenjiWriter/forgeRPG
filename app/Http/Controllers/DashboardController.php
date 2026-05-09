<?php

namespace App\Http\Controllers;

use App\Models\Island;
use App\Models\LevelDefinition;
use App\Models\MiningNode;
use App\Models\PlayerStat;
use App\Services\InventoryPricingService;
use App\Services\MiningService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MiningService $miningService,
        private readonly InventoryPricingService $inventoryPricingService,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user()->load(['stats', 'currentIsland']);

        /** @var PlayerStat $stats */
        $stats = $user->stats;

        $effectiveStamina = $this->computeStamina($stats);
        $now = now();

        $equippedPickaxe = $user->items()
            ->where('target_slot', 'pickaxe')
            ->where('equipped', true)
            ->latest('created_at')
            ->first();

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
            $this->miningService->spawnNodesForIsland($island);

            $node = MiningNode::where('island_id', $island->id)
                ->where('current_hp', '>', 0)
                ->whereNull('respawns_at')
                ->with('nodeType')
                ->oldest()
                ->first();
        }

        $inventory = $user->inventory()
            ->with('holdable')
            ->get()
            ->filter(fn ($slot) => $slot->holdable !== null)
            ->map(function ($slot) {
                $holdable = $slot->holdable;
                $type = class_basename($holdable);

                if ($type === 'Item') {
                    return [
                        'inventory_id' => $slot->id,
                        'id' => $holdable->id,
                        'name' => $holdable->name,
                        'quantity' => $slot->quantity,
                        'holdable_type' => 'item',
                        'base_sell_price' => $this->inventoryPricingService->resolveInventoryUnitPrice($slot),
                        'forge_grade' => $holdable->forge_grade,
                        'target_slot' => $holdable->target_slot,
                        'elemental_affinity' => $holdable->elemental_affinity,
                        'final_stats' => $holdable->final_stats ?? [],
                    ];
                }

                return [
                    'inventory_id' => $slot->id,
                    'id' => $holdable->id,
                    'name' => $holdable->name,
                    'quantity' => $slot->quantity,
                    'holdable_type' => 'ore',
                    'base_sell_price' => $this->inventoryPricingService->resolveInventoryUnitPrice($slot),
                    'rarity' => $holdable->rarity ?? 'common',
                ];
            });

        $nextLevelDef = LevelDefinition::where('level', '>', $user->level)
            ->orderBy('level')
            ->first();

        return Inertia::render('Dashboard', [
            'player' => [
                'id' => $user->id,
                'name' => $user->name,
                'level' => $user->level,
                'experience' => $user->experience,
                'gold' => $user->gold,
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
                'mining_power' => $equippedPickaxe->mining_dmg_bonus,
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
