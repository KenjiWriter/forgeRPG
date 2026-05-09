<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\OreType;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('game:give-ore {ore_id : The ore type ID to grant} {quantity=10 : Amount of ore to add} {user_id? : User ID (defaults to first user)}')]
#[Description('Grant ore to a user inventory for forge testing')]
class GiveOreCommand extends Command
{
    public function handle(): int
    {
        $oreId = (int) $this->argument('ore_id');
        $quantity = (int) $this->argument('quantity');

        if ($quantity < 1) {
            $this->error('Quantity must be at least 1.');

            return self::FAILURE;
        }

        $ore = OreType::query()->find($oreId);
        if (! $ore) {
            $this->error("Ore with ID {$oreId} was not found.");

            return self::FAILURE;
        }

        $userIdArgument = $this->argument('user_id');
        $user = $userIdArgument
            ? User::query()->find((int) $userIdArgument)
            : User::query()->orderBy('id')->first();

        if (! $user) {
            $this->error('No user found. Create a user first or provide a valid user_id.');

            return self::FAILURE;
        }

        $inventorySlot = Inventory::query()->firstOrNew([
            'user_id' => $user->id,
            'holdable_type' => OreType::class,
            'holdable_id' => $ore->id,
        ]);

        $inventorySlot->quantity = ($inventorySlot->quantity ?? 0) + $quantity;
        $inventorySlot->save();

        $this->info("Added {$quantity}x {$ore->name} to user #{$user->id}. New quantity: {$inventorySlot->quantity}.");

        return self::SUCCESS;
    }
}
