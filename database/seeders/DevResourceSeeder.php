<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\OreType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevResourceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if (! $user) {
            return;
        }

        OreType::query()->each(function (OreType $ore) use ($user): void {
            Inventory::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'holdable_type' => OreType::class,
                    'holdable_id' => $ore->id,
                ],
                [
                    'quantity' => 100,
                ]
            );
        });
    }
}
