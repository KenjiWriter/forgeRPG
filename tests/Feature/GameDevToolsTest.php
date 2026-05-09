<?php

use App\Models\Inventory;
use App\Models\Item;
use App\Models\OreType;
use App\Models\User;
use Database\Seeders\DevResourceSeeder;
use Database\Seeders\ItemTemplateSeeder;

use function Pest\Laravel\artisan;
use function Pest\Laravel\seed;

test('game give ore command adds ore to first user when user id is omitted', function () {
    $user = User::factory()->create();
    $ore = OreType::factory()->create(['name' => 'Test Ore']);

    artisan('game:give-ore', [
        'ore_id' => $ore->id,
    ])->assertExitCode(0);

    $inventory = Inventory::query()
        ->where('user_id', $user->id)
        ->where('holdable_type', OreType::class)
        ->where('holdable_id', $ore->id)
        ->first();

    expect($inventory)->not->toBeNull();
    expect($inventory->quantity)->toBe(10);
});

test('game give ore command adds to an explicit user and stacks quantity', function () {
    $firstUser = User::factory()->create();
    $targetUser = User::factory()->create();
    $ore = OreType::factory()->create(['name' => 'Stack Ore']);

    Inventory::query()->create([
        'user_id' => $targetUser->id,
        'holdable_type' => OreType::class,
        'holdable_id' => $ore->id,
        'quantity' => 5,
    ]);

    artisan('game:give-ore', [
        'ore_id' => $ore->id,
        'quantity' => 7,
        'user_id' => $targetUser->id,
    ])->assertExitCode(0);

    $targetQuantity = Inventory::query()
        ->where('user_id', $targetUser->id)
        ->where('holdable_type', OreType::class)
        ->where('holdable_id', $ore->id)
        ->value('quantity');

    $firstUserQuantity = Inventory::query()
        ->where('user_id', $firstUser->id)
        ->where('holdable_type', OreType::class)
        ->where('holdable_id', $ore->id)
        ->value('quantity');

    expect($targetQuantity)->toBe(12);
    expect($firstUserQuantity)->toBeNull();
});

test('item template seeder creates one template item per slot with base stats', function () {
    $user = User::factory()->create();

    seed(ItemTemplateSeeder::class);

    $templates = Item::query()->where('player_id', $user->id)->get();
    $slots = $templates->pluck('target_slot')->all();

    expect($templates->count())->toBeGreaterThanOrEqual(6);
    expect($slots)->toContain('helmet');
    expect($slots)->toContain('armor');
    expect($slots)->toContain('pants');
    expect($slots)->toContain('boots');
    expect($slots)->toContain('weapon');
    expect($slots)->toContain('pickaxe');

    $weapon = $templates->firstWhere('target_slot', 'weapon');
    expect($weapon)->not->toBeNull();
    expect($weapon->base_stats)->toHaveKey('Attack');
});

test('dev resource seeder grants first user one hundred of every ore type', function () {
    $user = User::factory()->create();
    $ores = OreType::factory()->count(4)->create();

    seed(DevResourceSeeder::class);

    foreach ($ores as $ore) {
        $quantity = Inventory::query()
            ->where('user_id', $user->id)
            ->where('holdable_type', OreType::class)
            ->where('holdable_id', $ore->id)
            ->value('quantity');

        expect($quantity)->toBe(100);
    }
});
