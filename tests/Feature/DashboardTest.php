<?php

use App\Models\Island;
use App\Models\MiningNode;
use App\Models\NodeType;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('dashboard passes player data to the view', function () {
    $user = User::factory()->create(['level' => 3, 'experience' => 250]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->has('player', fn ($player) => $player
                    ->where('id', $user->id)
                    ->where('name', $user->name)
                    ->where('level', 3)
                    ->where('experience', 250)
                    ->has('next_level_exp')
                )
                ->has('player_stats', fn ($stats) => $stats
                    ->has('stamina')
                    ->has('stamina_last_updated_at')
                    ->has('hp')
                )
                ->has('inventory')
                ->has('equipped_pickaxe'),
        );
});

test('dashboard shows the first available mining node on the island', function () {
    $user = User::factory()->create();
    $island = Island::factory()->create(['name' => 'Starter Isle']);
    $user->update(['current_island_id' => $island->id]);

    $nodeType = NodeType::factory()->create();
    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'current_hp' => 100,
        'max_hp' => 100,
        'respawns_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('island.name', 'Starter Isle')
                ->where('current_node.id', $node->id)
                ->where('current_node.max_hp', 100),
        );
});

test('dashboard shows null node when all nodes are respawning', function () {
    $user = User::factory()->create();
    $island = Island::factory()->create();
    $user->update(['current_island_id' => $island->id]);

    $nodeType = NodeType::factory()->create();
    MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'current_hp' => 0,
        'respawns_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('current_node', null),
        );
});
