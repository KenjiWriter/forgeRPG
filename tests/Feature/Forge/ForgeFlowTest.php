<?php

use App\Models\ForgeSession;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\OreType;
use App\Models\User;

beforeEach(function () {
    // Set up test data
    OreType::factory()
        ->count(5)
        ->sequence(
            ['id' => 1, 'name' => 'Copper', 'base_attack' => 5, 'base_defense' => 2, 'base_hp' => 3],
            ['id' => 2, 'name' => 'Iron', 'base_attack' => 10, 'base_defense' => 5, 'base_hp' => 8],
            ['id' => 3, 'name' => 'Gold', 'base_attack' => 15, 'base_defense' => 8, 'base_hp' => 12],
            ['id' => 4, 'name' => 'Mithril', 'base_attack' => 20, 'base_defense' => 12, 'base_hp' => 16],
            ['id' => 5, 'name' => 'Mythril', 'base_attack' => 25, 'base_defense' => 15, 'base_hp' => 20],
        )
        ->create();
});

test('forge init requires exactly 3 ores (Rule of 3)', function () {
    $user = User::factory()->create();

    // Test with 1 ore
    $this->actingAs($user)
        ->postJson(route('forge.init'), [
            'target_slot' => 'weapon',
            'ore_inputs' => [
                ['ore_type_id' => 1, 'quantity' => 10],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ore_inputs']);
});

test('forge init rejects fewer or more than 3 ores', function () {
    $user = User::factory()->create();

    // Test with 2 ores
    $this->actingAs($user)
        ->postJson(route('forge.init'), [
            'target_slot' => 'weapon',
            'ore_inputs' => [
                ['ore_type_id' => 1, 'quantity' => 10],
                ['ore_type_id' => 2, 'quantity' => 10],
            ],
        ])
        ->assertUnprocessable();

    // Test with 4 ores
    $this->actingAs($user)
        ->postJson(route('forge.init'), [
            'target_slot' => 'weapon',
            'ore_inputs' => [
                ['ore_type_id' => 1, 'quantity' => 10],
                ['ore_type_id' => 2, 'quantity' => 10],
                ['ore_type_id' => 3, 'quantity' => 10],
                ['ore_type_id' => 4, 'quantity' => 10],
            ],
        ])
        ->assertUnprocessable();
});

test('forge init rejects if player does not own sufficient ores', function () {
    $user = User::factory()->create();

    // Add only 5 copper, try to use 10
    Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => 1,
        'quantity' => 5,
    ]);

    Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => 2,
        'quantity' => 10,
    ]);

    Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => 3,
        'quantity' => 10,
    ]);

    $this->actingAs($user)
        ->postJson(route('forge.init'), [
            'target_slot' => 'weapon',
            'ore_inputs' => [
                ['ore_type_id' => 1, 'quantity' => 10],
                ['ore_type_id' => 2, 'quantity' => 10],
                ['ore_type_id' => 3, 'quantity' => 10],
            ],
        ])
        ->assertUnprocessable();
});

test('forge init creates session and consumes ores', function () {
    $user = User::factory()->create();

    // Set up inventory
    Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => 1,
        'quantity' => 20,
    ]);

    Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => 2,
        'quantity' => 15,
    ]);

    Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => 3,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('forge.init'), [
            'target_slot' => 'weapon',
            'ore_inputs' => [
                ['ore_type_id' => 1, 'quantity' => 10],
                ['ore_type_id' => 2, 'quantity' => 8],
                ['ore_type_id' => 3, 'quantity' => 5],
            ],
        ])
        ->assertOk()
        ->assertJsonStructure(['forge_session_id', 'target_slot', 'ore_inputs', 'status', 'next_stage']);

    $sessionId = $response->json('forge_session_id');

    // Verify session was created
    $session = ForgeSession::find($sessionId);
    expect($session)->not->toBeNull();
    expect($session->player_id)->toBe($user->id);
    expect($session->target_slot)->toBe('weapon');
    expect($session->status)->toBe('in_progress');

    // Verify ores were consumed
    $copper = Inventory::where('user_id', $user->id)
        ->where('holdable_id', 1)
        ->first();
    expect($copper->quantity)->toBe(10); // 20 - 10

    $iron = Inventory::where('user_id', $user->id)
        ->where('holdable_id', 2)
        ->first();
    expect($iron->quantity)->toBe(7); // 15 - 8

    $gold = Inventory::where('user_id', $user->id)
        ->where('holdable_id', 3)
        ->first();
    expect($gold->quantity)->toBe(5); // 10 - 5
});

test('forge complete computes grade and creates item', function () {
    $user = User::factory()->create(['level' => 5]);

    // Create session with ore inputs
    $session = ForgeSession::create([
        'id' => 'test-session-'.time(),
        'player_id' => $user->id,
        'target_slot' => 'weapon',
        'ore_inputs' => [
            ['ore_type_id' => 1, 'quantity' => 10],
            ['ore_type_id' => 2, 'quantity' => 8],
            ['ore_type_id' => 3, 'quantity' => 5],
        ],
        'status' => 'in_progress',
    ]);

    // Test with high scores (Grade IX should map to ~score 90)
    $response = $this->actingAs($user)
        ->postJson(route('forge.complete'), [
            'forge_session_id' => $session->id,
            'smelting_score' => 95,
            'smithing_score' => 90,
            'quench_score' => 85,
            'item_name' => 'Masterwork Blade',
        ])
        ->assertOk()
        ->assertJsonStructure(['item', 'grade', 'combined_score']);

    $combinedScore = $response->json('combined_score');
    $grade = $response->json('grade');

    // combined_score = (95 * 0.30) + (90 * 0.50) + (85 * 0.20)
    //               = 28.5 + 45 + 17 = 90.5 → rounds to 91
    // Grade 91 → Grade IX (86-95)
    expect($combinedScore)->toBe(91);
    expect($grade)->toBe(9);

    // Verify item was created
    $itemId = $response->json('item.id');
    $item = Item::find($itemId);
    expect($item)->not->toBeNull();
    expect($item->player_id)->toBe($user->id);
    expect($item->name)->toBe('Masterwork Blade');
    expect($item->target_slot)->toBe('weapon');
    expect($item->forge_grade)->toBe(9);

    // Verify session was marked complete
    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($session->combined_score)->toBe(91);
    expect($session->forge_grade)->toBe(9);
    expect($session->result_item_id)->toBe($itemId);
});

test('forge complete rejects if not owned by player', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $session = ForgeSession::create([
        'id' => 'test-session-'.time(),
        'player_id' => $user1->id,
        'target_slot' => 'weapon',
        'ore_inputs' => [
            ['ore_type_id' => 1, 'quantity' => 10],
            ['ore_type_id' => 2, 'quantity' => 8],
            ['ore_type_id' => 3, 'quantity' => 5],
        ],
        'status' => 'in_progress',
    ]);

    $this->actingAs($user2)
        ->postJson(route('forge.complete'), [
            'forge_session_id' => $session->id,
            'smelting_score' => 50,
            'smithing_score' => 50,
            'quench_score' => 50,
            'item_name' => 'Blade',
        ])
        ->assertForbidden();
});

test('forge signature is deterministic and sorted', function () {
    $user = User::factory()->create();

    $session1 = ForgeSession::create([
        'id' => 'test-session-1-'.time(),
        'player_id' => $user->id,
        'target_slot' => 'weapon',
        'ore_inputs' => [
            ['ore_type_id' => 3, 'quantity' => 5],
            ['ore_type_id' => 1, 'quantity' => 10],
            ['ore_type_id' => 2, 'quantity' => 8],
        ],
        'status' => 'in_progress',
    ]);

    $response1 = $this->actingAs($user)
        ->postJson(route('forge.complete'), [
            'forge_session_id' => $session1->id,
            'smelting_score' => 50,
            'smithing_score' => 50,
            'quench_score' => 50,
            'item_name' => 'Item 1',
        ]);

    // Create second session with same ores but different order
    $session2 = ForgeSession::create([
        'id' => 'test-session-2-'.time(),
        'player_id' => $user->id,
        'target_slot' => 'weapon',
        'ore_inputs' => [
            ['ore_type_id' => 1, 'quantity' => 10],
            ['ore_type_id' => 2, 'quantity' => 8],
            ['ore_type_id' => 3, 'quantity' => 5],
        ],
        'status' => 'in_progress',
    ]);

    $response2 = $this->actingAs($user)
        ->postJson(route('forge.complete'), [
            'forge_session_id' => $session2->id,
            'smelting_score' => 50,
            'smithing_score' => 50,
            'quench_score' => 50,
            'item_name' => 'Item 2',
        ]);

    $signature1 = Item::find($response1->json('item.id'))->forge_signature;
    $signature2 = Item::find($response2->json('item.id'))->forge_signature;

    // Both should have same signature since ores are sorted
    expect($signature1)->toBe($signature2);
    expect($signature1)->toBe('1:10|2:8|3:5');
});

test('grade mapping is correct for all grades', function () {
    $user = User::factory()->create(['level' => 5]);

    $testCases = [
        ['smelting' => 5, 'smithing' => 5, 'quench' => 5, 'expected_grade' => 1],   // combined ~5
        ['smelting' => 15, 'smithing' => 15, 'quench' => 15, 'expected_grade' => 2], // combined ~15
        ['smelting' => 25, 'smithing' => 25, 'quench' => 25, 'expected_grade' => 3], // combined ~25
        ['smelting' => 40, 'smithing' => 40, 'quench' => 40, 'expected_grade' => 4], // combined ~40
        ['smelting' => 60, 'smithing' => 60, 'quench' => 60, 'expected_grade' => 6], // combined ~60
        ['smelting' => 75, 'smithing' => 75, 'quench' => 75, 'expected_grade' => 7], // combined ~75
        ['smelting' => 90, 'smithing' => 90, 'quench' => 90, 'expected_grade' => 9], // combined ~91
    ];

    foreach ($testCases as $case) {
        $session = ForgeSession::create([
            'id' => 'test-'.md5(json_encode($case)).'-'.time(),
            'player_id' => $user->id,
            'target_slot' => 'weapon',
            'ore_inputs' => [
                ['ore_type_id' => 1, 'quantity' => 10],
                ['ore_type_id' => 2, 'quantity' => 8],
                ['ore_type_id' => 3, 'quantity' => 5],
            ],
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('forge.complete'), [
                'forge_session_id' => $session->id,
                'smelting_score' => $case['smelting'],
                'smithing_score' => $case['smithing'],
                'quench_score' => $case['quench'],
                'item_name' => 'Test Item',
            ]);

        expect($response->json('grade'))->toBe($case['expected_grade']);
    }
});
