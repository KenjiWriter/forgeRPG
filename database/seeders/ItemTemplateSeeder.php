<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if (! $user) {
            return;
        }

        $templates = [
            'helmet' => [
                'name' => 'Template Helmet',
                'base_stats' => ['Defense' => 3, 'HP' => 8],
            ],
            'armor' => [
                'name' => 'Template Armor',
                'base_stats' => ['Defense' => 5, 'HP' => 12],
            ],
            'pants' => [
                'name' => 'Template Pants',
                'base_stats' => ['Defense' => 2, 'Dodge' => 1],
            ],
            'boots' => [
                'name' => 'Template Boots',
                'base_stats' => ['Defense' => 2, 'Dodge' => 2],
            ],
            'weapon' => [
                'name' => 'Template Weapon',
                'base_stats' => ['Attack' => 10],
            ],
            'pickaxe' => [
                'name' => 'Template Pickaxe',
                'base_stats' => ['MiningDamage' => 8, 'MiningSpeed' => 2],
            ],
        ];

        foreach ($templates as $slot => $template) {
            Item::query()->updateOrCreate(
                [
                    'player_id' => $user->id,
                    'target_slot' => $slot,
                    'name' => $template['name'],
                ],
                [
                    'forge_grade' => 1,
                    'forge_signature' => "template:{$slot}",
                    'base_stats' => $template['base_stats'],
                    'final_stats' => $template['base_stats'],
                    'elemental_affinity' => 'neutral',
                    'equipped' => false,
                    'created_at' => now(),
                ]
            );
        }
    }
}
