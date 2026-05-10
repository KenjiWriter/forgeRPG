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
    private const BASE_STAMINA_REGEN_PER_SECOND = 10.0;

    public function __construct(
        private readonly MiningService $miningService,
        private readonly InventoryPricingService $inventoryPricingService,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user()->load(['stats', 'currentIsland', 'equipmentSlots.item']);

        /** @var PlayerStat $stats */
        $stats = $user->stats;

        $equippedPickaxe = $user->items()
            ->where('target_slot', 'pickaxe')
            ->where('equipped', true)
            ->latest('created_at')
            ->first();

        $effectiveStamina = $this->computeStamina($stats, $equippedPickaxe?->stamina_regen_bonus ?? 0.0);
        $now = now();

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
                        'is_equipped' => $holdable->equipped ?? false,
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
                'mining_speed' => ((float) $equippedPickaxe->mining_speed_bonus) / 100,
                'luck_bonus' => $equippedPickaxe->luck_bonus,
                'stamina_regen_bonus' => (float) $equippedPickaxe->stamina_regen_bonus,
            ] : null,
            'equipment' => $user->equipmentSlots()
                ->with('item')
                ->get()
                ->mapWithKeys(fn ($slot) => [
                    $slot->slot => $slot->item ? [
                        'id' => $slot->item->id,
                        'name' => $slot->item->name,
                        'mining_power' => $slot->item->mining_dmg_bonus ?? 0,
                        'mining_speed_bonus' => $slot->item->mining_speed_bonus ?? 0,
                        'luck_bonus' => $slot->item->luck_bonus ?? 0,
                        'stamina_regen_bonus' => (float) ($slot->item->stamina_regen_bonus ?? 0),
                        'hp_bonus' => $slot->item->hp_bonus ?? 0,
                        'defense_bonus' => $slot->item->defense_bonus ?? 0,
                        'attack_bonus' => $slot->item->attack_bonus ?? 0,
                        'dodge_bonus' => $slot->item->dodge_bonus ?? 0,
                        'crit_chance' => $slot->item->crit_chance ?? 0,
                        'attack_speed_bonus' => $slot->item->attack_speed_bonus ?? 0,
                        'elemental_affinity' => $slot->item->elemental_affinity,
                        'forge_grade' => $slot->item->forge_grade,
                        'final_stats' => $slot->item->final_stats ?? [],
                    ] : null,
                ])
                ->all(),
            'base_stats' => [
                'hp' => $stats->hp,
                'attack' => $stats->attack,
                'defense' => $stats->defense,
                'mining_speed' => $stats->mining_speed,
                'attack_speed' => $stats->attack_speed,
                'dodge' => $stats->dodge,
            ],
        ]);
    }

    private function computeStamina(PlayerStat $stats, float $staminaRegenBonus = 0.0): float
    {
        if ($stats->stamina_last_updated_at === null) {
            return (float) $stats->stamina;
        }

        $elapsed = max(0, now()->timestamp - $stats->stamina_last_updated_at->timestamp);
        $regenPerSecond = self::BASE_STAMINA_REGEN_PER_SECOND + $staminaRegenBonus;

        return min(100.0, (float) $stats->stamina + ($elapsed * $regenPerSecond));
    }
}
