<?php

use App\Models\EquipmentSlot;
use App\Models\Inventory;
use App\Models\Island;
use App\Models\Item;
use App\Models\Pickaxe;
use App\Models\User;
use App\Models\UserItem;
use Database\Seeders\PickaxeSeeder;

test('guests cannot access shop endpoints', function () {
    $pickaxe = Pickaxe::create([
        'name' => 'Stone Pickaxe',
        'rarity' => 'uncommon',
        'price' => 5000,
        'power' => 12,
        'luck_boost' => 5,
        'stamina_regen_bonus' => 0.2,
        'speed_modifier' => 1.10,
        'slots' => 1,
    ]);

    $this->get(route('shop'))->assertRedirect(route('login'));
    $this->post(route('shop.purchase', $pickaxe))->assertRedirect(route('login'));
});

test('shop index returns pickaxes for authenticated user', function () {
    $user = User::factory()->create();
    Pickaxe::create([
        'name' => 'Stone Pickaxe',
        'rarity' => 'uncommon',
        'price' => 5000,
        'power' => 12,
        'luck_boost' => 5,
        'stamina_regen_bonus' => 0.2,
        'speed_modifier' => 1.10,
        'slots' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('shop'))
        ->assertOk();
});

test('purchase fails when user has insufficient gold', function () {
    $user = User::factory()->create(['gold' => 4999, 'level' => 10]);
    $pickaxe = Pickaxe::create([
        'name' => 'Stone Pickaxe',
        'rarity' => 'uncommon',
        'price' => 5000,
        'power' => 12,
        'luck_boost' => 5,
        'stamina_regen_bonus' => 0.2,
        'speed_modifier' => 1.10,
        'slots' => 1,
    ]);

    $this->actingAs($user)
        ->postJson(route('shop.purchase', $pickaxe))
        ->assertUnprocessable();

    expect($user->fresh()->gold)->toBe(4999);
    expect(UserItem::query()->get()->count())->toBe(0);
});

test('purchase fails when user level is below required island level', function () {
    $island = Island::factory()->create(['min_level' => 8]);
    $user = User::factory()->create(['gold' => 999999, 'level' => 4]);
    $pickaxe = Pickaxe::create([
        'name' => 'Mythril Pickaxe',
        'rarity' => 'mythical',
        'price' => 10000,
        'power' => 60,
        'luck_boost' => 30,
        'stamina_regen_bonus' => 1.4,
        'speed_modifier' => 1.50,
        'slots' => 2,
        'requires_island_id' => $island->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('shop.purchase', $pickaxe))
        ->assertUnprocessable();

    expect($user->fresh()->gold)->toBe(999999);
    expect(UserItem::query()->get()->count())->toBe(0);
});

test('successful purchase deducts gold and creates ownership plus inventory item', function () {
    $user = User::factory()->create(['gold' => 8000, 'level' => 9]);
    $pickaxe = Pickaxe::create([
        'name' => 'Stone Pickaxe',
        'rarity' => 'uncommon',
        'price' => 5000,
        'power' => 12,
        'luck_boost' => 5,
        'stamina_regen_bonus' => 0.2,
        'speed_modifier' => 1.20,
        'slots' => 1,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('shop.purchase', $pickaxe))
        ->assertOk();

    $response->assertJsonPath('mining_speed', 1.2);

    $purchasedItemId = $response->json('item_id');

    expect($user->fresh()->gold)->toBe(3000);

    $this->assertDatabaseHas('user_items', [
        'user_id' => $user->id,
        'pickaxe_id' => $pickaxe->id,
        'quantity' => 1,
    ]);

    $this->assertDatabaseHas('items', [
        'id' => $purchasedItemId,
        'player_id' => $user->id,
        'target_slot' => 'pickaxe',
        'mining_speed_bonus' => 120,
        'mining_dmg_bonus' => 12,
        'luck_bonus' => 5,
        'stamina_regen_bonus' => 0.2,
    ]);

    $this->assertDatabaseHas('inventories', [
        'user_id' => $user->id,
        'holdable_type' => Item::class,
        'holdable_id' => $purchasedItemId,
        'quantity' => 1,
    ]);
});

test('purchased pickaxe item can be equipped from inventory', function () {
    $user = User::factory()->create(['gold' => 99999, 'level' => 12]);
    $pickaxe = Pickaxe::create([
        'name' => 'Diamond Pickaxe',
        'rarity' => 'legendary',
        'price' => 30000,
        'power' => 60,
        'luck_boost' => 35,
        'stamina_regen_bonus' => 1.0,
        'speed_modifier' => 1.55,
        'slots' => 2,
    ]);

    $purchaseResponse = $this->actingAs($user)
        ->postJson(route('shop.purchase', $pickaxe))
        ->assertOk();

    $itemId = (string) $purchaseResponse->json('item_id');

    $inventory = Inventory::query()
        ->where('user_id', $user->id)
        ->where('holdable_type', Item::class)
        ->where('holdable_id', $itemId)
        ->firstOrFail();

    $this->actingAs($user)
        ->postJson(route('inventory.equip', $inventory))
        ->assertOk();

    $slot = EquipmentSlot::query()
        ->where('user_id', $user->id)
        ->where('slot', 'pickaxe')
        ->first();

    expect($slot)->not->toBeNull();
    expect((string) $slot?->item_id)->toBe($itemId);
});

test('rerunning pickaxe seeder upgrades existing pickaxe item mining power', function () {
    $user = User::factory()->create();

    Item::query()
        ->where('player_id', $user->id)
        ->where('target_slot', 'pickaxe')
        ->update(['equipped' => false]);

    $legacyItem = Item::create([
        'player_id' => $user->id,
        'name' => 'Stone Pickaxe',
        'target_slot' => 'pickaxe',
        'forge_grade' => 1,
        'mining_dmg_bonus' => 25,
        'luck_bonus' => 0,
        'stamina_regen_bonus' => 0.0,
        'equipped' => true,
        'created_at' => now(),
    ]);

    $this->seed(PickaxeSeeder::class);

    expect($legacyItem->fresh()?->mining_dmg_bonus)->toBe(40);
});
