<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            NodeTypeSeeder::class,
            OreTypeSeeder::class,
            PickaxeSeeder::class,
            NodeSourceSeeder::class,
            MiningNodeSeeder::class,
        ]);
    }
}
