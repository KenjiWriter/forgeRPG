<?php

namespace App\Services;

use App\Events\LevelUp;
use App\Events\NodeDepleted;
use App\Events\NodeSpawned;
use App\Events\NodeUpdated;
use App\Events\StaminaUpdated;
use App\Models\Inventory;
use App\Models\Island;
use App\Models\Item;
use App\Models\LevelDefinition;
use App\Models\MiningNode;
use App\Models\OreType;
use App\Models\PlayerStat;
use App\Models\User;
use Carbon\CarbonImmutable;

class MiningService
{
    /** 3 pts/sec → ~33 seconds for a full 0→100 recharge */
    private const STAMINA_REGEN_PER_SECOND = 3;

    /** Fixed stamina cost per hit */
    private const STAMINA_COST_PER_HIT = 30.0;

    /** Minimum stamina required to initiate a hit */
    private const STAMINA_MINIMUM_THRESHOLD = 10;

    /**
     * Process a mining hit on a node.
     *
     * @return array{
     *     damage_dealt: int,
     *     node_hp_remaining: int,
     *     is_destroyed: bool,
     *     stamina_remaining: float,
     *     loot: list<array{ore_id: int, name: string}>|null,
     *     exp_gained: int,
     *     new_player_exp: int,
     *     level_up: bool
     * }
     */
    public function hit(User $user, MiningNode $node): array
    {
        if ($node->isRespawning() || $node->current_hp === 0) {
            abort(422, 'This node is not available.');
        }

        $stats = $user->stats;
        $equippedPickaxe = $this->getEquippedPickaxe($user);

        $effectiveStamina = $this->calculateCurrentStamina($stats);

        if ($effectiveStamina < self::STAMINA_MINIMUM_THRESHOLD) {
            abort(422, 'Not enough stamina.');
        }

        // Linear scaling: FinalDamage = BaseDamage * (CurrentStamina / 100)
        // A hit at 50% stamina deals exactly 50% of base damage.
        $multiplier  = $effectiveStamina / 100.0;
        $pickaxePower = $equippedPickaxe?->mining_dmg_bonus ?? 5;
        $damage = max(1, (int) round(($pickaxePower + $stats->mining_speed) * $multiplier));

        // Fixed 30-stamina cost per hit; clamp to 0 so it never goes negative.
        $newStamina = max(0.0, $effectiveStamina - self::STAMINA_COST_PER_HIT);
        $staminaUpdatedAt = CarbonImmutable::now();

        $stats->stamina = $newStamina;
        $stats->stamina_last_updated_at = $staminaUpdatedAt;
        $stats->save();

        $newHp = max(0, $node->current_hp - $damage);
        $node->current_hp = $newHp;
        $node->save();

        broadcast(new NodeUpdated($node->id, $newHp, $node->island_id));
        broadcast(new StaminaUpdated($user->id, $newStamina, $staminaUpdatedAt));

        $loot = null;
        $expGained = 0;
        $levelUp = false;
        $newPlayerExp = $user->experience;

        if ($newHp === 0) {
            $loot = $this->rollNodeLoot($node, $user, $equippedPickaxe);

            $expGained = $node->nodeType->tier * 10;
            $newPlayerExp = $user->experience + $expGained;
            $previousLevel = $user->level;

            $user->experience = $newPlayerExp;
            $newLevel = $this->calculateLevel($newPlayerExp);

            if ($newLevel > $previousLevel) {
                $user->level = $newLevel;
                $levelUp = true;
                broadcast(new LevelUp($user->id, $newLevel));
            }

            $user->save();

            $respawnsAt = CarbonImmutable::now()->addMinutes($node->nodeType->respawn_minutes);
            $node->respawns_at = $respawnsAt;
            $node->save();

            broadcast(new NodeDepleted($node->id, $respawnsAt, $node->nodeType->slug, $node->island_id));
        }

        return [
            'damage_dealt' => $damage,
            'node_hp_remaining' => $newHp,
            'is_destroyed' => $newHp === 0,
            'stamina_remaining' => $newStamina,
            'loot' => $loot,
            'exp_gained' => $expGained,
            'new_player_exp' => $newPlayerExp,
            'level_up' => $levelUp,
        ];
    }

    private function getEquippedPickaxe(User $user): ?Item
    {
        return $user->equipmentSlots()
            ->where('slot', 'pickaxe')
            ->with('item')
            ->first()
            ?->item;
    }

    private function calculateCurrentStamina(PlayerStat $stats): float
    {
        if ($stats->stamina_last_updated_at === null) {
            return (float) $stats->stamina;
        }

        $elapsed = max(0, CarbonImmutable::now()->timestamp - $stats->stamina_last_updated_at->timestamp);
        $regenerated = $elapsed * self::STAMINA_REGEN_PER_SECOND;

        return min(100.0, $stats->stamina + $regenerated);
    }

    /**
     * @return list<array{ore_id: int, name: string}>
     */
    private function rollNodeLoot(MiningNode $node, User $user, ?Item $pickaxe): array
    {
        $luckBoost = $pickaxe?->luck_bonus ?? 0;
        $eligibleOres = $node->nodeType->oreTypes;
        $loot = [];

        foreach ($eligibleOres as $ore) {
            $adjustedChance = max(1, (int) floor($ore->base_chance / (1 + $luckBoost / 100)));
            $roll = random_int(1, $adjustedChance);

            if ($roll === 1) {
                $this->addToInventory($user->id, $ore);
                $loot[] = ['ore_id' => $ore->id, 'name' => $ore->name];
            }
        }

        return $loot;
    }

    private function addToInventory(int $userId, OreType $ore): void
    {
        $existing = Inventory::where('user_id', $userId)
            ->where('holdable_type', OreType::class)
            ->where('holdable_id', $ore->id)
            ->first();

        if ($existing) {
            $existing->increment('quantity');
        } else {
            Inventory::create([
                'user_id' => $userId,
                'holdable_type' => OreType::class,
                'holdable_id' => $ore->id,
                'quantity' => 1,
            ]);
        }
    }

    private function calculateLevel(int $experience): int
    {
        $levelDef = LevelDefinition::where('exp_required', '<=', $experience)
            ->orderBy('level', 'desc')
            ->first();

        return $levelDef?->level ?? 1;
    }

    /**
     * Ensure an island has at least $minimumPerType active nodes for each of its configured node types.
     * Returns the number of nodes spawned.
     */
    public function spawnNodesForIsland(Island $island, int $minimumPerType = 1): int
    {
        $nodeTypes = $island->nodeTypes;
        $spawned = 0;

        foreach ($nodeTypes as $nodeType) {
            // Only count nodes that are genuinely mineable: have HP and are not on a respawn timer.
            $activeCount = MiningNode::where('island_id', $island->id)
                ->where('node_type_id', $nodeType->id)
                ->where('current_hp', '>', 0)
                ->whereNull('respawns_at')
                ->count();

            $needed = max(0, $minimumPerType - $activeCount);

            for ($i = 0; $i < $needed; $i++) {
                $newNode = MiningNode::create([
                    'island_id'    => $island->id,
                    'node_type_id' => $nodeType->id,
                    'max_hp'       => $nodeType->base_hp,
                    'current_hp'   => $nodeType->base_hp,
                    'respawns_at'  => null,
                ]);

                // Broadcast to presence channel so connected clients hot-swap
                // the respawning placeholder with the fresh node.
                $newNode->load('nodeType');
                broadcast(new NodeSpawned($newNode));
                $spawned++;
            }
        }

        return $spawned;
    }
}
