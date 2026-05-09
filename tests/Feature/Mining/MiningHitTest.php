<?php

use App\Events\LevelUp;
use App\Events\NodeDepleted;
use App\Events\NodeSpawned;
use App\Events\NodeUpdated;
use App\Events\StaminaUpdated;
use App\Models\Island;
use App\Models\LevelDefinition;
use App\Models\MiningNode;
use App\Models\NodeType;
use App\Models\OreType;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([NodeUpdated::class, NodeDepleted::class, NodeSpawned::class, StaminaUpdated::class, LevelUp::class]);
});

test('guests cannot hit a mining node', function () {
    $node = MiningNode::factory()->create();

    $this->postJson(route('mining.hit'), [
        'node_id' => $node->id,
        'stamina_percent' => 100,
    ])->assertUnauthorized();
});

test('hit request validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('mining.hit'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['node_id']);
});

test('hit request validates node_id exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('mining.hit'), [
            'node_id' => 99999,
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['node_id']);
});

test('a hit reduces node hp and returns correct structure', function () {
    $user = User::factory()->create();
    $user->stats->update(['stamina' => 100, 'stamina_last_updated_at' => now()]);

    $nodeType = NodeType::factory()->create(['tier' => 1, 'base_hp' => 300, 'respawn_minutes' => 5]);
    $island = Island::factory()->create();
    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'max_hp' => 300,
        'current_hp' => 300,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('mining.hit'), [
            'node_id' => $node->id,
        ])->assertOk()
        ->assertJsonStructure([
            'damage_dealt',
            'node_hp_remaining',
            'is_destroyed',
            'stamina_remaining',
            'loot',
            'exp_gained',
            'new_player_exp',
            'level_up',
        ]);

    expect($response->json('is_destroyed'))->toBeFalse();
    expect($response->json('damage_dealt'))->toBeGreaterThan(0);
    expect($response->json('node_hp_remaining'))->toBeLessThan(300);

    $this->assertDatabaseHas('mining_nodes', [
        'id' => $node->id,
        'current_hp' => $response->json('node_hp_remaining'),
    ]);
});

test('NodeUpdated and StaminaUpdated are broadcast on every hit', function () {
    $user = User::factory()->create();
    $user->stats->update(['stamina' => 100, 'stamina_last_updated_at' => now()]);

    $nodeType = NodeType::factory()->create(['tier' => 1, 'respawn_minutes' => 5]);
    $island = Island::factory()->create();
    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'current_hp' => 500,
    ]);

    $this->actingAs($user)
        ->postJson(route('mining.hit'), [
            'node_id' => $node->id,
        ])->assertOk();

    Event::assertDispatched(NodeUpdated::class, fn ($e) => $e->nodeId === $node->id);
    Event::assertDispatched(StaminaUpdated::class, fn ($e) => $e->userId === $user->id);
});

test('destroying a node via hit returns destroyed state but no loot before collect', function () {
    $user = User::factory()->create();
    $user->stats->update(['stamina' => 100, 'stamina_last_updated_at' => now()]);

    LevelDefinition::create(['level' => 1, 'exp_required' => 0, 'unlock_note' => null]);

    $ore = OreType::factory()->guaranteed()->create();
    $nodeType = NodeType::factory()->create(['tier' => 2, 'respawn_minutes' => 5]);
    $nodeType->oreTypes()->attach($ore->id);

    $island = Island::factory()->create();
    $node = MiningNode::factory()->almostDead(1)->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'max_hp' => 100,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('mining.hit'), [
            'node_id' => $node->id,
        ])->assertOk();

    expect($response->json('is_destroyed'))->toBeTrue();
    expect($response->json('exp_gained'))->toBe(0);
    expect($response->json('node_hp_remaining'))->toBe(0);
    expect($response->json('loot'))->toBeNull();

    $this->assertDatabaseHas('mining_nodes', [
        'id' => $node->id,
        'current_hp' => 0,
    ]);

    $this->assertDatabaseHas('mining_nodes', [
        'id' => $node->id,
        'respawns_at' => null,
    ]);

    Event::assertNotDispatched(NodeDepleted::class);
});

test('collecting a destroyed node awards loot, exp, and broadcasts NodeDepleted', function () {
    $user = User::factory()->create();
    $user->stats->update(['stamina' => 100, 'stamina_last_updated_at' => now()]);

    LevelDefinition::create(['level' => 1, 'exp_required' => 0, 'unlock_note' => null]);

    $ore = OreType::factory()->guaranteed()->create();
    $nodeType = NodeType::factory()->create(['tier' => 2, 'respawn_minutes' => 5]);
    $nodeType->oreTypes()->attach($ore->id);

    $island = Island::factory()->create();
    $user->update(['current_island_id' => $island->id]);

    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'max_hp' => 100,
        'current_hp' => 0,
        'respawns_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('mining.collect'), [
            'node_id' => $node->id,
        ])->assertOk()
        ->assertJsonStructure([
            'loot',
            'exp_gained',
            'new_player_exp',
            'level_up',
            'next_node',
        ]);

    expect($response->json('exp_gained'))->toBe(20);
    expect($response->json('loot'))->toBeArray()->not->toBeEmpty();

    $this->assertDatabaseHas('inventories', [
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $ore->id,
    ]);

    $this->assertDatabaseMissing('mining_nodes', [
        'id' => $node->id,
        'respawns_at' => null,
    ]);

    Event::assertDispatched(NodeDepleted::class, fn ($e) => $e->nodeId === $node->id);
    Event::assertDispatched(NodeSpawned::class);
});

test('collecting a destroyed node that triggers level up broadcasts LevelUp', function () {
    $user = User::factory()->create();
    $user->stats->update(['stamina' => 100, 'stamina_last_updated_at' => now()]);

    LevelDefinition::create(['level' => 1, 'exp_required' => 0, 'unlock_note' => null]);
    LevelDefinition::create(['level' => 2, 'exp_required' => 10, 'unlock_note' => null]);

    $nodeType = NodeType::factory()->create(['tier' => 2, 'respawn_minutes' => 5]);
    $island = Island::factory()->create();
    $user->update(['current_island_id' => $island->id]);

    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'max_hp' => 100,
        'current_hp' => 0,
        'respawns_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('mining.collect'), [
            'node_id' => $node->id,
        ])->assertOk();

    expect($response->json('level_up'))->toBeTrue();

    Event::assertDispatched(LevelUp::class, fn ($e) => $e->userId === $user->id && $e->newLevel === 2);
});

test('cannot hit a node that is respawning', function () {
    $user = User::factory()->create();
    $user->stats->update(['stamina' => 100, 'stamina_last_updated_at' => now()]);

    $node = MiningNode::factory()->respawning()->create();

    $this->actingAs($user)
        ->postJson(route('mining.hit'), [
            'node_id' => $node->id,
        ])->assertUnprocessable();
});

test('collect request validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('mining.collect'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['node_id']);
});

test('cannot collect node that is not destroyed', function () {
    $user = User::factory()->create();
    $island = Island::factory()->create();
    $user->update(['current_island_id' => $island->id]);

    $nodeType = NodeType::factory()->create(['tier' => 1, 'respawn_minutes' => 5]);
    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'current_hp' => 10,
        'respawns_at' => null,
    ]);

    $this->actingAs($user)
        ->postJson(route('mining.collect'), ['node_id' => $node->id])
        ->assertUnprocessable();
});

test('low stamina results in lower damage multiplier', function () {
    $user = User::factory()->create();

    // Set stamina low but above the minimum threshold.
    $user->stats->update([
        'stamina' => 11,
        'stamina_last_updated_at' => now(),
        'mining_speed' => 0,
    ]);

    $nodeType = NodeType::factory()->create(['tier' => 1, 'respawn_minutes' => 5]);
    $island = Island::factory()->create();
    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
        'current_hp' => 5000,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('mining.hit'), [
            'node_id' => $node->id,
        ])->assertOk();

    // Pickaxe power=5, mining_speed=0, multiplier=0.11 → dmg = round(5 * 0.11) = 1
    expect($response->json('damage_dealt'))->toBe(1);
});

test('cannot hit when stamina is below minimum threshold', function () {
    $user = User::factory()->create();
    $user->stats->update(['stamina' => 3, 'stamina_last_updated_at' => now()]);

    $nodeType = NodeType::factory()->create(['tier' => 1, 'respawn_minutes' => 5]);
    $island = Island::factory()->create();
    $node = MiningNode::factory()->create([
        'island_id' => $island->id,
        'node_type_id' => $nodeType->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('mining.hit'), [
            'node_id' => $node->id,
        ])->assertUnprocessable();
});
