<?php

namespace Database\Seeders;

use App\Models\Island;
use App\Models\Pickaxe;
use Illuminate\Database\Seeder;

class PickaxeSeeder extends Seeder
{
    public function run(): void
    {
        $forgottenKingdomId = Island::query()->where('name', 'Forgotten Kingdom')->value('id');
        $volcanicRiftId = Island::query()->where('name', 'The Volcanic Rift')->value('id');

        $pickaxes = [
            [
                'name' => 'Wooden Pickaxe',
                'rarity' => 'common',
                'price' => 0,
                'power' => 5,
                'luck_boost' => 0,
                'stamina_regen_bonus' => 0.0,
                'speed_modifier' => 1.00,
                'slots' => 0,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Stone Pickaxe',
                'rarity' => 'uncommon',
                'price' => 5000,
                'power' => 12,
                'luck_boost' => 5,
                'stamina_regen_bonus' => 0.2,
                'speed_modifier' => 1.10,
                'slots' => 1,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Iron Pickaxe',
                'rarity' => 'rare',
                'price' => 20000,
                'power' => 22,
                'luck_boost' => 10,
                'stamina_regen_bonus' => 0.4,
                'speed_modifier' => 1.20,
                'slots' => 1,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Golden Pickaxe',
                'rarity' => 'epic',
                'price' => 80000,
                'power' => 38,
                'luck_boost' => 20,
                'stamina_regen_bonus' => 0.7,
                'speed_modifier' => 1.35,
                'slots' => 2,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Diamond Pickaxe',
                'rarity' => 'legendary',
                'price' => 300000,
                'power' => 60,
                'luck_boost' => 35,
                'stamina_regen_bonus' => 1.0,
                'speed_modifier' => 1.55,
                'slots' => 2,
                'requires_island_id' => null,
            ],
            [
                'name' => 'Mythril Pickaxe',
                'rarity' => 'mythical',
                'price' => 1200000,
                'power' => 90,
                'luck_boost' => 50,
                'stamina_regen_bonus' => 1.4,
                'speed_modifier' => 1.75,
                'slots' => 3,
                'requires_island_id' => $forgottenKingdomId,
            ],
            [
                'name' => 'Volcanic Pickaxe',
                'rarity' => 'mythical',
                'price' => 5000000,
                'power' => 130,
                'luck_boost' => 70,
                'stamina_regen_bonus' => 2.0,
                'speed_modifier' => 2.00,
                'slots' => 3,
                'requires_island_id' => $volcanicRiftId,
            ],
        ];

        foreach ($pickaxes as $pickaxe) {
            Pickaxe::firstOrCreate(['name' => $pickaxe['name']], $pickaxe);
        }
    }
}
