<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\EquipmentSlot;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\User;
use App\Services\InventoryPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryPricingService $inventoryPricingService) {}

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

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($inventory->user_id !== $user->id) {
            abort(403);
        }

        return $this->saleResponse(
            $user,
            $this->processSale($user, $inventory, $validated['quantity'] ?? null),
        );
    }

    public function sellByItemId(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'inventory_id' => ['nullable', 'integer'],
            'item_id' => ['required'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $inventory = null;

        if (! empty($validated['inventory_id'])) {
            $inventory = Inventory::query()
                ->whereKey((int) $validated['inventory_id'])
                ->where('user_id', $user->id)
                ->with('holdable')
                ->first();
        }

        if ($inventory === null) {
            $inventory = Inventory::query()
                ->where('user_id', $user->id)
                ->where('holdable_id', (string) $validated['item_id'])
                ->with('holdable')
                ->orderByDesc('quantity')
                ->first();
        }

        if ($inventory === null) {
            abort(404, 'Inventory item not found.');
        }

        return $this->saleResponse(
            $user,
            $this->processSale($user, $inventory, $validated['quantity'] ?? null),
        );
    }

    /**
     * @return array{unit_price:int,total_value:int,sold_quantity:int,remaining_quantity:int}
     */
    private function processSale(User $user, Inventory $inventory, ?int $requestedQuantity = null): array
    {
        return DB::transaction(function () use ($inventory, $user, $requestedQuantity): array {
            $lockedInventory = Inventory::query()
                ->whereKey($inventory->id)
                ->with('holdable')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInventory->user_id !== $user->id) {
                abort(403);
            }

            $sellQuantity = (int) ($requestedQuantity ?? $lockedInventory->quantity);

            if ($sellQuantity > $lockedInventory->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['The requested quantity exceeds the available stack.'],
                ]);
            }

            $unitPrice = $this->inventoryPricingService->resolveInventoryUnitPrice($lockedInventory);
            $totalValue = $unitPrice * $sellQuantity;
            $remainingQuantity = $lockedInventory->quantity - $sellQuantity;

            $user->increment('gold', $totalValue);

            if ($remainingQuantity <= 0) {
                $lockedInventory->delete();
            } else {
                $lockedInventory->update(['quantity' => $remainingQuantity]);
            }

            return [
                'unit_price' => $unitPrice,
                'total_value' => $totalValue,
                'sold_quantity' => $sellQuantity,
                'remaining_quantity' => max(0, $remainingQuantity),
            ];
        });
    }

    /**
     * @param  array{unit_price:int,total_value:int,sold_quantity:int,remaining_quantity:int}  $result
     */
    private function saleResponse(User $user, array $result): JsonResponse
    {
        $user->refresh();

        return response()->json([
            'message' => 'Item sold.',
            'unit_price' => $result['unit_price'],
            'total_value' => $result['total_value'],
            'sold_quantity' => $result['sold_quantity'],
            'remaining_quantity' => $result['remaining_quantity'],
            'gold' => $user->gold,
        ]);
    }
}
