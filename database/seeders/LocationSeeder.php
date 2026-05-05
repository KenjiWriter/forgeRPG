<?php

namespace Database\Seeders;

use App\Models\Island;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $islands = [
            [
                'name' => "Stonewake's Cross",
                'min_level' => 1,
                'unlock_condition' => null,
            ],
            [
                'name' => 'Forgotten Kingdom',
                'min_level' => 20,
                'unlock_condition' => ['min_level' => 20],
            ],
            [
                'name' => 'The Volcanic Rift',
                'min_level' => 40,
                'unlock_condition' => ['min_level' => 40],
            ],
            [
                'name' => 'Frostspire Expanse',
                'min_level' => 60,
                'unlock_condition' => ['min_level' => 60],
            ],
        ];

        foreach ($islands as $island) {
            Island::firstOrCreate(['name' => $island['name']], $island);
        }
    }
}
