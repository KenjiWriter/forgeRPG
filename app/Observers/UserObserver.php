<?php

namespace App\Observers;

use App\Models\EquipmentSlot;
use App\Models\Island;
use App\Models\Item;
use App\Models\Pickaxe;
use App\Models\PlayerStat;
use App\Models\User;
use App\Services\MiningService;

class UserObserver
{
    public function created(User $user): void
    {
        PlayerStat::create([
            'user_id' => $user->id,
            'hp' => 100,
            'attack' => 10,
            'defense' => 5,
            'mining_speed' => 10,
            'attack_speed' => 10,
            'dodge' => 0,
            'stamina' => 100,
            'stamina_last_updated_at' => now(),
        ]);

        $woodenPickaxe = Pickaxe::where('name', 'Wooden Pickaxe')->first();

        $starterItem = Item::create([
            'player_id' => $user->id,
            'name' => 'Wooden Pickaxe',
            'target_slot' => 'pickaxe',
            'forge_grade' => 1,
            'mining_dmg_bonus' => $woodenPickaxe?->power ?? 5,
            'luck_bonus' => $woodenPickaxe?->luck_boost ?? 0,
            'equipped' => true,
            'created_at' => now(),
        ]);

        $slots = ['helmet', 'armor', 'pants', 'boots', 'weapon', 'pickaxe'];

        foreach ($slots as $slot) {
            EquipmentSlot::create([
                'user_id' => $user->id,
                'slot' => $slot,
                'item_id' => $slot === 'pickaxe' ? $starterItem->id : null,
                'updated_at' => now(),
            ]);
        }

        $startingIsland = Island::where('name', "Stonewake's Cross")->first()
            ?? Island::where('min_level', 1)->orderBy('id')->first();

        if ($startingIsland) {
            $user->current_island_id = $startingIsland->id;
            $user->saveQuietly();

            app(MiningService::class)->spawnNodesForIsland($startingIsland);
        }
    }
}
