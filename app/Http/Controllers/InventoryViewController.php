<?php

namespace App\Http\Controllers;

use App\Services\InventoryPricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryViewController extends Controller
{
    public function __construct(private readonly InventoryPricingService $inventoryPricingService) {}

    public function index(Request $request): Response
    {
        $user = $request->user()->load(['stats', 'equipmentSlots.item', 'items']);

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
            })
            ->values();

        $equipment = $user->equipmentSlots()
            ->with('item')
            ->get()
            ->mapWithKeys(fn ($slot) => [
                $slot->slot => $slot->item ? [
                    'id' => $slot->item->id,
                    'name' => $slot->item->name,
                    'mining_power' => $slot->item->mining_dmg_bonus ?? 0,
                    'mining_speed_bonus' => $slot->item->mining_speed_bonus ?? 0,
                    'luck_bonus' => $slot->item->luck_bonus ?? 0,
                    'stamina_regen_bonus' => $slot->item->stamina_regen_bonus ?? 0,
                    'hp_bonus' => $slot->item->hp_bonus ?? 0,
                    'defense_bonus' => $slot->item->defense_bonus ?? 0,
                    'attack_bonus' => $slot->item->attack_bonus ?? 0,
                    'elemental_affinity' => $slot->item->elemental_affinity,
                    'forge_grade' => $slot->item->forge_grade,
                    'final_stats' => $slot->item->final_stats ?? [],
                ] : null,
            ])
            ->all();

        $stats = $user->stats;

        return Inertia::render('Inventory', [
            'player' => [
                'id' => $user->id,
                'name' => $user->name,
                'level' => $user->level,
                'gold' => $user->gold,
            ],
            'inventory_items' => $inventory,
            'equipment' => $equipment,
            'player_stats' => [
                'hp' => $stats->hp,
                'attack' => $stats->attack,
                'defense' => $stats->defense,
                'mining_speed' => $stats->mining_speed,
                'attack_speed' => $stats->attack_speed,
                'dodge' => $stats->dodge,
            ],
        ]);
    }
}
