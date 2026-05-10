<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Pickaxe;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $ownershipByPickaxe = $user->userItems()
            ->pluck('quantity', 'pickaxe_id');

        $pickaxes = Pickaxe::query()
            ->with('requiredIsland:id,min_level')
            ->orderBy('price')
            ->get()
            ->map(function (Pickaxe $pickaxe) use ($ownershipByPickaxe): array {
                $minimumLevel = max(1, (int) ($pickaxe->requiredIsland?->min_level ?? 1));

                return [
                    'id' => $pickaxe->id,
                    'name' => $pickaxe->name,
                    'rarity' => $pickaxe->rarity,
                    'mining_power' => $pickaxe->power,
                    'mining_speed' => (float) $pickaxe->speed_modifier,
                    'luck_bonus' => $pickaxe->luck_boost,
                    'stamina_regen_bonus' => (float) $pickaxe->stamina_regen_bonus,
                    'buy_price' => $pickaxe->price,
                    'min_level' => $minimumLevel,
                    'owned_quantity' => (int) ($ownershipByPickaxe[$pickaxe->id] ?? 0),
                ];
            })
            ->values();

        return Inertia::render('ShopView', [
            'player' => [
                'id' => $user->id,
                'level' => $user->level,
                'gold' => $user->gold,
            ],
            'shop_items' => $pickaxes,
        ]);
    }

    public function purchase(Request $request, Pickaxe $pickaxe): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        $result = DB::transaction(function () use ($authUser, $pickaxe): array {
            $lockedUser = User::query()
                ->whereKey($authUser->id)
                ->lockForUpdate()
                ->firstOrFail();

            $pickaxe->loadMissing('requiredIsland:id,min_level');

            $buyPrice = (int) $pickaxe->price;
            $minimumLevel = max(1, (int) ($pickaxe->requiredIsland?->min_level ?? 1));

            if ($lockedUser->gold < $buyPrice) {
                abort(422, 'Not enough gold to buy this pickaxe.');
            }

            if ($lockedUser->level < $minimumLevel) {
                abort(422, 'Your level is too low for this pickaxe.');
            }

            $lockedUser->decrement('gold', $buyPrice);

            $ownedPickaxe = UserItem::query()
                ->where('user_id', $lockedUser->id)
                ->where('pickaxe_id', $pickaxe->id)
                ->lockForUpdate()
                ->first();

            if ($ownedPickaxe === null) {
                UserItem::create([
                    'user_id' => $lockedUser->id,
                    'pickaxe_id' => $pickaxe->id,
                    'quantity' => 1,
                ]);
            } else {
                $ownedPickaxe->increment('quantity', 1);
            }

            $createdItem = Item::create([
                'player_id' => $lockedUser->id,
                'name' => $pickaxe->name,
                'target_slot' => 'pickaxe',
                'forge_grade' => $this->mapRarityToGrade($pickaxe->rarity),
                'forge_signature' => sprintf('shop:pickaxe:%d', $pickaxe->id),
                'mining_speed_bonus' => (int) round($pickaxe->speed_modifier * 100),
                'mining_dmg_bonus' => $pickaxe->power,
                'luck_bonus' => $pickaxe->luck_boost,
                'stamina_regen_bonus' => $pickaxe->stamina_regen_bonus,
                'final_stats' => [
                    'mining_speed_bonus' => (int) round($pickaxe->speed_modifier * 100),
                    'mining_power' => $pickaxe->power,
                    'mining_speed' => (float) $pickaxe->speed_modifier,
                    'luck_bonus' => $pickaxe->luck_boost,
                    'stamina_regen_bonus' => (float) $pickaxe->stamina_regen_bonus,
                ],
                'equipped' => false,
                'created_at' => now(),
            ]);

            $inventory = Inventory::create([
                'user_id' => $lockedUser->id,
                'holdable_type' => Item::class,
                'holdable_id' => $createdItem->id,
                'quantity' => 1,
            ]);

            return [
                'gold' => (int) $lockedUser->fresh()->gold,
                'inventory_id' => $inventory->id,
                'item_id' => $createdItem->id,
                'pickaxe_id' => $pickaxe->id,
                'pickaxe_name' => $pickaxe->name,
                'buy_price' => $buyPrice,
                'mining_power' => (int) $pickaxe->power,
                'mining_speed' => (float) $pickaxe->speed_modifier,
                'luck_bonus' => (int) $pickaxe->luck_boost,
                'stamina_regen_bonus' => (float) $pickaxe->stamina_regen_bonus,
            ];
        });

        return response()->json([
            'message' => 'Pickaxe purchased successfully.',
            ...$result,
        ]);
    }

    private function mapRarityToGrade(string $rarity): int
    {
        return match (strtolower($rarity)) {
            'mythical' => 9,
            'legendary' => 7,
            'epic' => 5,
            'rare' => 3,
            'uncommon' => 2,
            default => 1,
        };
    }
}
