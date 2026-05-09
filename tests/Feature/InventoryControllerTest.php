<?php

use App\Models\EquipmentSlot;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\OreType;
use App\Models\User;
use App\Services\InventoryPricingService;
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
    $oreType = OreType::factory()->create(['price' => 45]);
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 5,
    ]);

    $this->actingAs($user)
        ->postJson(route('inventory.sell', $inventory))
        ->assertOk()
        ->assertJsonFragment([
            'message' => 'Item sold.',
            'sold_quantity' => 5,
            'remaining_quantity' => 0,
            'unit_price' => 45,
            'total_value' => 225,
        ]);

    expect(Inventory::find($inventory->id))->toBeNull();
    expect($user->fresh()->gold)->toBe(225);
});

test('a player can sell inventory via api item payload', function () {
    $user = User::factory()->create();
    $oreType = OreType::factory()->create(['price' => 22]);
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 4,
    ]);

    $this->actingAs($user)
        ->postJson('/api/inventory/sell', [
            'item_id' => $oreType->id,
            'quantity' => 3,
        ])
        ->assertOk()
        ->assertJsonFragment([
            'sold_quantity' => 3,
            'remaining_quantity' => 1,
            'unit_price' => 22,
            'total_value' => 66,
        ]);

    expect(Inventory::find($inventory->id)?->quantity)->toBe(1);
    expect($user->fresh()->gold)->toBe(66);
});

test('api sell uses inventory_id to target the correct stack', function () {
    $user = User::factory()->create();
    $oreType = OreType::factory()->create(['price' => 10]);

    $smallStack = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 1,
    ]);

    $bigStack = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 5,
    ]);

    $this->actingAs($user)
        ->postJson('/api/inventory/sell', [
            'inventory_id' => $smallStack->id,
            'item_id' => $oreType->id,
            'quantity' => 1,
        ])
        ->assertOk()
        ->assertJsonFragment([
            'sold_quantity' => 1,
            'remaining_quantity' => 0,
            'total_value' => 10,
        ]);

    expect(Inventory::find($smallStack->id))->toBeNull();
    expect(Inventory::find($bigStack->id)?->quantity)->toBe(5);
    expect($user->fresh()->gold)->toBe(10);
});

test('a player can partially sell an ore stack from inventory', function () {
    $user = User::factory()->create();
    $oreType = OreType::factory()->create(['price' => 30]);
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 5,
    ]);

    $this->actingAs($user)
        ->postJson(route('inventory.sell', $inventory), ['quantity' => 2])
        ->assertOk()
        ->assertJsonFragment([
            'sold_quantity' => 2,
            'remaining_quantity' => 3,
            'unit_price' => 30,
            'total_value' => 60,
        ]);

    expect(Inventory::find($inventory->id))->not->toBeNull();
    expect(Inventory::find($inventory->id)?->quantity)->toBe(3);
    expect($user->fresh()->gold)->toBe(60);
});

test('a player cannot sell more quantity than they own', function () {
    $user = User::factory()->create();
    $oreType = OreType::factory()->create(['price' => 30]);
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $oreType->id,
        'quantity' => 2,
    ]);

    $this->actingAs($user)
        ->postJson(route('inventory.sell', $inventory), ['quantity' => 3])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity']);

    expect(Inventory::find($inventory->id)?->quantity)->toBe(2);
    expect($user->fresh()->gold)->toBe(0);
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

test('a player selling a forged item receives grade-based value', function () {
    $user = User::factory()->create();
    $item = Item::create([
        'id' => Str::uuid(),
        'player_id' => $user->id,
        'name' => 'Mythic Hammer',
        'target_slot' => 'weapon',
        'forge_grade' => 8,
        'attack_bonus' => 18,
        'defense_bonus' => 9,
        'hp_bonus' => 12,
        'elemental_affinity' => 'fire',
    ]);
    $inventory = Inventory::create([
        'user_id' => $user->id,
        'holdable_type' => Item::class,
        'holdable_id' => $item->id,
        'quantity' => 1,
    ]);

    $expectedUnitPrice = app(InventoryPricingService::class)->calculateForgedItemUnitPrice($item);

    $this->actingAs($user)
        ->postJson(route('inventory.sell', $inventory), ['quantity' => 1])
        ->assertOk()
        ->assertJsonFragment([
            'unit_price' => $expectedUnitPrice,
            'total_value' => $expectedUnitPrice,
            'sold_quantity' => 1,
            'remaining_quantity' => 0,
        ]);

    expect(Inventory::find($inventory->id))->toBeNull();
    expect($user->fresh()->gold)->toBe($expectedUnitPrice);
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
