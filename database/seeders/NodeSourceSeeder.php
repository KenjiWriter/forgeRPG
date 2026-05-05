<?php

namespace Database\Seeders;

use App\Models\Island;
use App\Models\NodeType;
use App\Models\OreType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NodeSourceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedNodeOreSourcePivot();
        $this->seedLocationNodeTypesPivot();
    }

    private function seedNodeOreSourcePivot(): void
    {
        // Keyed by node slug => array of eligible ore names (mining nodes only)
        $nodeOreMap = [
            'pebble' => ['Stone', 'Sand Stone', 'Copper', 'Iron', 'Poopite'],
            'rock' => ['Sand Stone', 'Copper', 'Iron', 'Tin', 'Silver', 'Mushroomite', 'Bananite', 'Cardboardite', 'Poopite'],
            'boulder' => ['Copper', 'Iron', 'Tin', 'Silver', 'Gold', 'Mushroomite', 'Platinum', 'Bananite', 'Cardboardite', 'Aite', 'Poopite'],
            'basalt_rock' => ['Silver', 'Gold', 'Platinum', 'Cobalt', 'Titanium', 'Lapis Lazuli', 'Eye Ore'],
            'basalt_core' => ['Cobalt', 'Titanium', 'Lapis Lazuli', 'Quartz', 'Amethyst', 'Topaz', 'Diamond', 'Sapphire', 'Cuprite', 'Emerald', 'Eye Ore'],
            'basalt_vein' => ['Quartz', 'Amethyst', 'Topaz', 'Diamond', 'Sapphire', 'Cuprite', 'Emerald', 'Ruby', 'Rivalite', 'Uranium', 'Mythril', 'Lightite', 'Eye Ore'],
            'volcanic_rock' => ['Volcanic Rock', 'Topaz', 'Cuprite', 'Obsidian', 'Rivalite', 'Eye Ore', 'Fireite', 'Magmaite', 'Demonite', 'Darkryte'],
            'icy_pebble' => ['Emerald', 'Ruby', 'Rivalite', 'Uranium', 'Mythril', 'Lightite'],
            'icy_rock' => ['Uranium', 'Mythril', 'Lightite'],
        ];

        $nodeTypes = NodeType::all()->keyBy('slug');
        $oreTypes = OreType::all()->keyBy('name');

        $rows = [];
        foreach ($nodeOreMap as $slug => $oreNames) {
            $nodeTypeId = $nodeTypes->get($slug)?->id;
            if ($nodeTypeId === null) {
                continue;
            }

            foreach ($oreNames as $oreName) {
                $oreTypeId = $oreTypes->get($oreName)?->id;
                if ($oreTypeId === null) {
                    continue;
                }

                $rows[] = [
                    'node_type_id' => $nodeTypeId,
                    'ore_type_id' => $oreTypeId,
                ];
            }
        }

        DB::table('node_type_ore_sources')->upsert(
            $rows,
            ['node_type_id', 'ore_type_id']
        );
    }

    private function seedLocationNodeTypesPivot(): void
    {
        // Keyed by island name => array of node slugs that spawn there
        $locationNodeMap = [
            "Stonewake's Cross" => ['pebble', 'rock', 'boulder'],
            'Forgotten Kingdom' => ['basalt_rock', 'basalt_core', 'basalt_vein'],
            'The Volcanic Rift' => ['volcanic_rock'],
            'Frostspire Expanse' => ['icy_pebble', 'icy_rock'],
        ];

        $islands = Island::all()->keyBy('name');
        $nodeTypes = NodeType::all()->keyBy('slug');

        $rows = [];
        foreach ($locationNodeMap as $islandName => $slugs) {
            $islandId = $islands->get($islandName)?->id;
            if ($islandId === null) {
                continue;
            }

            foreach ($slugs as $slug) {
                $nodeTypeId = $nodeTypes->get($slug)?->id;
                if ($nodeTypeId === null) {
                    continue;
                }

                $rows[] = [
                    'island_id' => $islandId,
                    'node_type_id' => $nodeTypeId,
                ];
            }
        }

        DB::table('location_node_types')->upsert(
            $rows,
            ['island_id', 'node_type_id']
        );
    }
}
