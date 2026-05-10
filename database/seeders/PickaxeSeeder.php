<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Pickaxe;
use Illuminate\Database\Seeder;

class PickaxeSeeder extends Seeder
{
    public function run(): void
    {
        $pickaxes = [
            [
                'name' => 'Wooden Pickaxe',
                'rarity' => 'common',
                'price' => 0,
                'power' => 15,
                'luck_boost' => 0,
                'stamina_regen_bonus' => 0.1,
                'speed_modifier' => 1.00,
                'slots' => 0,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Stone Pickaxe',
                'rarity' => 'uncommon',
                'price' => 500,
                'power' => 40,
                'luck_boost' => 5,
                'stamina_regen_bonus' => 0.3,
                'speed_modifier' => 1.20,
                'slots' => 1,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Iron Pickaxe',
                'rarity' => 'rare',
                'price' => 2500,
                'power' => 100,
                'luck_boost' => 10,
                'stamina_regen_bonus' => 0.6,
                'speed_modifier' => 1.50,
                'slots' => 1,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Steel Pickaxe',
                'rarity' => 'epic',
                'price' => 10000,
                'power' => 250,
                'luck_boost' => 20,
                'stamina_regen_bonus' => 1.0,
                'speed_modifier' => 1.80,
                'slots' => 2,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Mythril Pickaxe',
                'rarity' => 'legendary',
                'price' => 50000,
                'power' => 600,
                'luck_boost' => 35,
                'stamina_regen_bonus' => 1.5,
                'speed_modifier' => 2.20,
                'slots' => 2,
                'requires_island_id' => null,
            ],
        ];

        foreach ($pickaxes as $pickaxe) {
            Pickaxe::query()->updateOrCreate(
                ['name' => $pickaxe['name']],
                $pickaxe,
            );

            Item::query()
                ->where('target_slot', 'pickaxe')
                ->where('name', $pickaxe['name'])
                ->update([
                    'mining_dmg_bonus' => $pickaxe['power'],
                ]);
        }
    }
}
