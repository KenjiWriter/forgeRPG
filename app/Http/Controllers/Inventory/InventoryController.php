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

        return $this->equipResponse($user, $inventory);
    }

    public function equipByItemId(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'inventory_id' => ['nullable', 'integer'],
            'item_id' => ['required'],
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
                ->where('holdable_type', Item::class)
                ->with('holdable')
                ->first();
        }

        if ($inventory === null) {
            abort(404, 'Inventory item not found.');
        }

        return $this->equipResponse($user, $inventory);
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

    private function equipResponse(User $user, Inventory $inventory): JsonResponse
    {
        $result = DB::transaction(function () use ($inventory, $user): array {
            $lockedInventory = Inventory::query()
                ->whereKey($inventory->id)
                ->with('holdable')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInventory->user_id !== $user->id) {
                abort(403);
            }

            if ($lockedInventory->holdable_type !== (new Item)->getMorphClass()) {
                abort(422, 'Only items can be equipped.');
            }

            /** @var Item|null $item */
            $item = Item::query()
                ->whereKey((string) $lockedInventory->holdable_id)
                ->where('player_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                abort(404, 'Item not found.');
            }

            $slot = $item->target_slot;

            Item::query()
                ->where('player_id', $user->id)
                ->where('target_slot', $slot)
                ->where('equipped', true)
                ->update(['equipped' => false]);

            $item->equipped = true;
            $item->save();

            EquipmentSlot::updateOrCreate(
                ['user_id' => $user->id, 'slot' => $slot],
                ['item_id' => $item->id, 'updated_at' => now()],
            );

            $equipmentState = EquipmentSlot::query()
                ->where('user_id', $user->id)
                ->with('item')
                ->get()
                ->mapWithKeys(fn (EquipmentSlot $equipmentSlot) => [
                    $equipmentSlot->slot => $equipmentSlot->item ? [
                        'id' => $equipmentSlot->item->id,
                        'name' => $equipmentSlot->item->name,
                    ] : null,
                ])
                ->all();

            $equippedPickaxe = Item::query()
                ->where('player_id', $user->id)
                ->where('target_slot', 'pickaxe')
                ->where('equipped', true)
                ->latest('created_at')
                ->first();

            return [
                'slot' => $slot,
                'equipped_item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'target_slot' => $item->target_slot,
                ],
                'equipped_pickaxe' => $equippedPickaxe ? [
                    'id' => $equippedPickaxe->id,
                    'name' => $equippedPickaxe->name,
                    'mining_power' => $equippedPickaxe->mining_dmg_bonus,
                    'luck_bonus' => $equippedPickaxe->luck_bonus,
                    'stamina_regen_bonus' => (float) $equippedPickaxe->stamina_regen_bonus,
                ] : null,
                'equipment' => $equipmentState,
            ];
        });

        return response()->json([
            'message' => 'Item equipped.',
            ...$result,
        ]);
    }
}
