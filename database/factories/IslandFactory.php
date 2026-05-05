<?php

namespace Database\Factories;

use App\Models\Island;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Island>
 */
class IslandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'min_level' => 1,
            'unlock_condition' => ['rune_ids' => [], 'min_level' => 1],
        ];
    }
}
