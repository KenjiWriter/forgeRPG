<?php

namespace Database\Factories;

use App\Models\OreType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OreType>
 */
class OreTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'rarity' => fake()->randomElement(['common', 'uncommon', 'rare', 'epic', 'legendary', 'mythical']),
            'base_chance' => fake()->numberBetween(1, 100),
            'multiplier' => fake()->randomFloat(2, 0.2, 6.0),
            'price' => fake()->numberBetween(100, 10000),
            'elemental_affinity' => fake()->randomElement(['fire', 'water', 'earth', 'void', 'neutral']),
            'base_attack' => fake()->numberBetween(0, 50),
            'base_defense' => fake()->numberBetween(0, 50),
            'base_hp' => fake()->numberBetween(0, 100),
        ];
    }

    public function guaranteed(): static
    {
        return $this->state(fn (array $attributes) => [
            'base_chance' => 1,
        ]);
    }

    public function impossible(): static
    {
        return $this->state(fn (array $attributes) => [
            'base_chance' => PHP_INT_MAX,
        ]);
    }
}
