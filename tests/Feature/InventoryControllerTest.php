<?php

use App\Models\EquipmentSlot;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\OreType;
use App\Models\User;
use Illuminate\Support\Str;

test('guests cannot equip or sell inventory items', function () {
    $user = User::factory()->create();
    $oreType = OreType::factory()->create();
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 3,
    ]);

    $this->postJson(route('inventory.equip', $inventory))->assertUnauthorized();
    $this->postJson(route('inventory.sell', $inventory))->assertUnauthorized();
});

test('a player can sell an ore from their inventory', function () {
    $user = User::factory()->create();
    $oreType = OreType::factory()->create();
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 5,
    ]);

    $this->actingAs($user)
        ->postJson(route('inventory.sell', $inventory))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Item sold.']);

    expect(Inventory::find($inventory->id))->toBeNull();
});

test('a player cannot sell another players inventory item', function () {
    $owner = User::factory()->create();
    $thief = User::factory()->create();
    $oreType = OreType::factory()->create();
    $inventory = Inventory::create([
        'user_id' => $owner->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 1,
    ]);

    $this->actingAs($thief)
        ->postJson(route('inventory.sell', $inventory))
        ->assertForbidden();

    expect(Inventory::find($inventory->id))->not->toBeNull();
});

test('a player can equip an item from their inventory', function () {
    $user = User::factory()->create();
    $item = Item::create([
        'id' => Str::uuid(),
        'player_id' => $user->id,
        'name' => 'Iron Helmet',
        'target_slot' => 'helmet',
        'forge_grade' => 5,
        'elemental_affinity' => 'neutral',
    ]);
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => Item::class,
        'holdable_id' => $item->id,
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->postJson(route('inventory.equip', $inventory))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Item equipped.', 'slot' => 'helmet']);

    expect(EquipmentSlot::where('user_id', $user->id)->where('slot', 'helmet')->first())
        ->not->toBeNull()
        ->item_id->toBe($item->id);
});

test('equipping an ore returns a 422 error', function () {
    $user = User::factory()->create();
    $oreType = OreType::factory()->create();
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 2,
    ]);

    $this->actingAs($user)
        ->postJson(route('inventory.equip', $inventory))
        ->assertUnprocessable();
});
