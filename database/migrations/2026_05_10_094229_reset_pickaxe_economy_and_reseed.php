<?php

use App\Models\Item;
use Database\Seeders\PickaxeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Remove all historical pickaxe ownership records.
        DB::table('user_items')->truncate();

        $pickaxeItemIds = Item::query()
            ->where('target_slot', 'pickaxe')
            ->pluck('id');

        if ($pickaxeItemIds->isNotEmpty()) {
            DB::table('equipment_slots')
                ->whereIn('item_id', $pickaxeItemIds)
                ->update(['item_id' => null]);

            DB::table('inventories')
                ->where('holdable_type', Item::class)
                ->whereIn('holdable_id', $pickaxeItemIds)
                ->delete();

            DB::table('items')
                ->whereIn('id', $pickaxeItemIds)
                ->delete();
        }

        DB::table('pickaxes')->truncate();

        if (Schema::hasTable('shop_items')) {
            DB::table('shop_items')->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $this->callSeeder(PickaxeSeeder::class);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a one-way data correction migration.
    }

    private function callSeeder(string $seederClass): void
    {
        app($seederClass)->run();
    }
};
