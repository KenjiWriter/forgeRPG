<?php

namespace Database\Seeders;

use App\Models\OreType;
use Illuminate\Database\Seeder;

class OreTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Prices in cents. base_chance = denominator X of "1 in X" drop.
        // Multiplier rounded to decimal(4,2). Tin 0.425 → 0.43.
        $ores = [
            // Zone 1 — Stonewake's Cross
            ['name' => 'Stone',         'rarity' => 'common',    'base_chance' => 1,    'multiplier' => 0.20, 'price' => 300],
            ['name' => 'Sand Stone',    'rarity' => 'common',    'base_chance' => 2,    'multiplier' => 0.25, 'price' => 375],
            ['name' => 'Copper',        'rarity' => 'common',    'base_chance' => 3,    'multiplier' => 0.30, 'price' => 450],
            ['name' => 'Iron',          'rarity' => 'common',    'base_chance' => 5,    'multiplier' => 0.35, 'price' => 525],
            ['name' => 'Tin',           'rarity' => 'uncommon',  'base_chance' => 7,    'multiplier' => 0.43, 'price' => 638],
            ['name' => 'Silver',        'rarity' => 'uncommon',  'base_chance' => 12,   'multiplier' => 0.50, 'price' => 750],
            ['name' => 'Gold',          'rarity' => 'uncommon',  'base_chance' => 16,   'multiplier' => 0.65, 'price' => 1950],
            ['name' => 'Mushroomite',   'rarity' => 'rare',      'base_chance' => 22,   'multiplier' => 0.80, 'price' => 1200],
            ['name' => 'Platinum',      'rarity' => 'rare',      'base_chance' => 28,   'multiplier' => 0.80, 'price' => 1200],
            ['name' => 'Bananite',      'rarity' => 'uncommon',  'base_chance' => 30,   'multiplier' => 0.85, 'price' => 1275],
            ['name' => 'Cardboardite',  'rarity' => 'common',    'base_chance' => 31,   'multiplier' => 0.70, 'price' => 1050],
            ['name' => 'Aite',          'rarity' => 'epic',      'base_chance' => 44,   'multiplier' => 1.10, 'price' => 1650],
            ['name' => 'Poopite',       'rarity' => 'epic',      'base_chance' => 131,  'multiplier' => 1.20, 'price' => 1800],
            // Zone 2 — Forgotten Kingdom
            ['name' => 'Cobalt',        'rarity' => 'uncommon',  'base_chance' => 37,   'multiplier' => 1.00, 'price' => 1500],
            ['name' => 'Titanium',      'rarity' => 'uncommon',  'base_chance' => 50,   'multiplier' => 1.15, 'price' => 1725],
            ['name' => 'Lapis Lazuli',  'rarity' => 'uncommon',  'base_chance' => 73,   'multiplier' => 1.30, 'price' => 1950],
            ['name' => 'Quartz',        'rarity' => 'rare',      'base_chance' => 90,   'multiplier' => 1.50, 'price' => 2250],
            ['name' => 'Amethyst',      'rarity' => 'rare',      'base_chance' => 115,  'multiplier' => 1.65, 'price' => 2475],
            ['name' => 'Topaz',         'rarity' => 'rare',      'base_chance' => 143,  'multiplier' => 1.75, 'price' => 2625],
            ['name' => 'Diamond',       'rarity' => 'rare',      'base_chance' => 192,  'multiplier' => 2.00, 'price' => 3000],
            ['name' => 'Sapphire',      'rarity' => 'rare',      'base_chance' => 247,  'multiplier' => 2.25, 'price' => 3375],
            ['name' => 'Cuprite',       'rarity' => 'epic',      'base_chance' => 303,  'multiplier' => 2.43, 'price' => 3645],
            ['name' => 'Emerald',       'rarity' => 'epic',      'base_chance' => 363,  'multiplier' => 2.55, 'price' => 3825],
            ['name' => 'Ruby',          'rarity' => 'epic',      'base_chance' => 487,  'multiplier' => 2.95, 'price' => 4425],
            ['name' => 'Rivalite',      'rarity' => 'epic',      'base_chance' => 569,  'multiplier' => 3.33, 'price' => 4995],
            ['name' => 'Uranium',       'rarity' => 'legendary', 'base_chance' => 777,  'multiplier' => 3.00, 'price' => 6600],
            ['name' => 'Mythril',       'rarity' => 'legendary', 'base_chance' => 813,  'multiplier' => 3.50, 'price' => 5250],
            ['name' => 'Lightite',      'rarity' => 'legendary', 'base_chance' => 3333, 'multiplier' => 4.60, 'price' => 6900],
            ['name' => 'Eye Ore',       'rarity' => 'legendary', 'base_chance' => 1333, 'multiplier' => 4.00, 'price' => 6000],
            // Zone 3 — The Volcanic Rift
            ['name' => 'Volcanic Rock', 'rarity' => 'rare',      'base_chance' => 55,   'multiplier' => 1.55, 'price' => 2325],
            ['name' => 'Obsidian',      'rarity' => 'epic',      'base_chance' => 333,  'multiplier' => 2.35, 'price' => 3525],
            ['name' => 'Fireite',       'rarity' => 'legendary', 'base_chance' => 2187, 'multiplier' => 4.50, 'price' => 6750],
            ['name' => 'Magmaite',      'rarity' => 'legendary', 'base_chance' => 3003, 'multiplier' => 5.00, 'price' => 7500],
            ['name' => 'Demonite',      'rarity' => 'mythical',  'base_chance' => 3666, 'multiplier' => 5.50, 'price' => 8250],
            ['name' => 'Darkryte',      'rarity' => 'mythical',  'base_chance' => 6655, 'multiplier' => 6.30, 'price' => 9450],
            // Enemy Drops
            ['name' => 'Boneite',       'rarity' => 'rare',      'base_chance' => 222,  'multiplier' => 1.20, 'price' => 1800],
            ['name' => 'Dark Boneite',  'rarity' => 'rare',      'base_chance' => 555,  'multiplier' => 2.25, 'price' => 3375],
            ['name' => 'Slimite',       'rarity' => 'epic',      'base_chance' => 247,  'multiplier' => 2.25, 'price' => 3375],
        ];

        foreach ($ores as $ore) {
            OreType::firstOrCreate(
                ['name' => $ore['name']],
                array_merge($ore, ['elemental_affinity' => 'neutral'])
            );
        }
    }
}
