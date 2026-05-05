<?php

namespace Database\Factories;

use App\Models\Island;
use App\Models\MiningNode;
use App\Models\NodeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MiningNode>
 */
class MiningNodeFactory extends Factory
{
    public function definition(): array
    {
        $maxHp = fake()->numberBetween(100, 1000);

        return [
            'island_id' => Island::factory(),
            'node_type_id' => NodeType::factory(),
            'max_hp' => $maxHp,
            'current_hp' => $maxHp,
            'respawns_at' => null,
        ];
    }

    public function almostDead(int $hp = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'current_hp' => $hp,
        ]);
    }

    public function respawning(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_hp' => 0,
            'respawns_at' => now()->addMinutes(5),
        ]);
    }
}
