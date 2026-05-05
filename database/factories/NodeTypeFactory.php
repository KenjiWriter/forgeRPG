<?php

namespace Database\Factories;

use App\Models\NodeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NodeType>
 */
class NodeTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'tier' => fake()->numberBetween(1, 6),
            'base_hp' => fake()->numberBetween(100, 5000),
            'respawn_minutes' => fake()->numberBetween(2, 40),
        ];
    }
}
