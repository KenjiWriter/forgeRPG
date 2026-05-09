<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\EquipmentSlot;
use App\Models\Inventory;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Equip an item from the player's inventory into its target slot.
     */
    public function equip(Request $request, Inventory $inventory): JsonResponse
    {
        $user = $request->user();

        if ($inventory->user_id !== $user->id) {
            abort(403);
        }

        if ($inventory->holdable_type !== (new Item)->getMorphClass()) {
            abort(422, 'Only items can be equipped.');
        }

        /** @var Item $item */
        $item = $inventory->holdable;

        if ($item === null) {
            abort(404, 'Item not found.');
        }

        EquipmentSlot::updateOrCreate(
            ['user_id' => $user->id, 'slot' => $item->target_slot],
            ['item_id' => $item->id],
        );

        return response()->json(['message' => 'Item equipped.', 'slot' => $item->target_slot]);
    }

    /**
     * Sell (remove) an item or ore from the player's inventory.
     */
    public function sell(Request $request, Inventory $inventory): JsonResponse
    {
        $user = $request->user();

        if ($inventory->user_id !== $user->id) {
            abort(403);
        }

        $inventory->delete();

        return response()->json(['message' => 'Item sold.']);
    }
}
