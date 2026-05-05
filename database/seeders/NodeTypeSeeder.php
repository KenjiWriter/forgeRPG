<?php

namespace Database\Seeders;

use App\Models\NodeType;
use Illuminate\Database\Seeder;

class NodeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $nodeTypes = [
            ['slug' => 'pebble',       'name' => 'Pebble',       'tier' => 1, 'base_hp' => 100,  'respawn_minutes' => 2],
            ['slug' => 'rock',         'name' => 'Rock',         'tier' => 2, 'base_hp' => 300,  'respawn_minutes' => 5],
            ['slug' => 'boulder',      'name' => 'Boulder',      'tier' => 3, 'base_hp' => 800,  'respawn_minutes' => 10],
            ['slug' => 'basalt_rock',  'name' => 'Basalt Rock',  'tier' => 4, 'base_hp' => 1500, 'respawn_minutes' => 15],
            ['slug' => 'basalt_core',  'name' => 'Basalt Core',  'tier' => 5, 'base_hp' => 2500, 'respawn_minutes' => 20],
            ['slug' => 'basalt_vein',  'name' => 'Basalt Vein',  'tier' => 6, 'base_hp' => 4000, 'respawn_minutes' => 30],
            ['slug' => 'volcanic_rock', 'name' => 'Volcanic Rock', 'tier' => 5, 'base_hp' => 3000, 'respawn_minutes' => 25],
            ['slug' => 'icy_pebble',   'name' => 'Icy Pebble',   'tier' => 5, 'base_hp' => 3500, 'respawn_minutes' => 25],
            ['slug' => 'icy_rock',     'name' => 'Icy Rock',     'tier' => 6, 'base_hp' => 5500, 'respawn_minutes' => 40],
        ];

        foreach ($nodeTypes as $nodeType) {
            NodeType::firstOrCreate(['slug' => $nodeType['slug']], $nodeType);
        }
    }
}
