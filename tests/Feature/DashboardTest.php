<?php

use App\Models\Island;
use App\Models\Item;
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
                    ->etc()
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

test('dashboard includes equipped pickaxe mining speed multiplier', function () {
    $user = User::factory()->create();

    Item::query()
        ->where('player_id', $user->id)
        ->where('target_slot', 'pickaxe')
        ->update(['equipped' => false]);

    Item::create([
        'player_id' => $user->id,
        'name' => 'Stone Pickaxe',
        'target_slot' => 'pickaxe',
        'forge_grade' => 2,
        'mining_speed_bonus' => 120,
        'mining_dmg_bonus' => 600,
        'luck_bonus' => 5,
        'stamina_regen_bonus' => 0.3,
        'equipped' => true,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('equipped_pickaxe.mining_speed', 1.2)
                ->where('equipped_pickaxe.mining_power', 600),
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

test('dashboard fills 100 stamina in 10 seconds at base regen without equipment bonus', function () {
    $user = User::factory()->create();

    Item::query()
        ->where('player_id', $user->id)
        ->where('target_slot', 'pickaxe')
        ->update(['equipped' => false]);

    Item::create([
        'player_id' => $user->id,
        'name' => 'Training Pickaxe',
        'target_slot' => 'pickaxe',
        'forge_grade' => 1,
        'mining_speed_bonus' => 100,
        'mining_dmg_bonus' => 5,
        'luck_bonus' => 0,
        'stamina_regen_bonus' => 0.0,
        'equipped' => true,
        'created_at' => now(),
    ]);

    $user->stats()->update([
        'stamina' => 0.0,
        'stamina_last_updated_at' => now()->subSeconds(10),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('player_stats.stamina', 100),
        );
});

test('dashboard adds equipped stamina regen bonus to the 10 per second base', function () {
    $user = User::factory()->create();

    Item::query()
        ->where('player_id', $user->id)
        ->where('target_slot', 'pickaxe')
        ->update(['equipped' => false]);

    Item::create([
        'player_id' => $user->id,
        'name' => 'Swift Pickaxe',
        'target_slot' => 'pickaxe',
        'forge_grade' => 2,
        'mining_speed_bonus' => 110,
        'mining_dmg_bonus' => 8,
        'luck_bonus' => 0,
        'stamina_regen_bonus' => 1.5,
        'equipped' => true,
        'created_at' => now(),
    ]);

    $user->stats()->update([
        'stamina' => 0.0,
        'stamina_last_updated_at' => now()->subSeconds(5),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('player_stats.stamina', 57.5),
        );
});
