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
use App\Models\NodeType;
use App\Models\OreType;
use App\Models\PlayerStat;
use App\Models\User;
use Carbon\CarbonImmutable;

class MiningService
{
    /** 10 pts/sec → 10 seconds for a full 0→100 recharge */
    private const BASE_STAMINA_REGEN_PER_SECOND = 10;

    /** New nodes spawn with 70% of the node type base HP. */
    private const NODE_HP_SCALING_FACTOR = 0.7;

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
     *     loot: null,
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

        $effectiveStamina = $this->calculateCurrentStamina($stats, $equippedPickaxe);

        if ($effectiveStamina < self::STAMINA_MINIMUM_THRESHOLD) {
            abort(422, 'Not enough stamina.');
        }

        // Linear scaling: FinalDamage = BaseDamage * (CurrentStamina / 100)
        // A hit at 50% stamina deals exactly 50% of base damage.
        $multiplier = $effectiveStamina / 100.0;
        $miningPower = (int) ($equippedPickaxe?->mining_dmg_bonus ?? 0);
        $damage = max(1, (int) round(($miningPower + $stats->mining_speed) * $multiplier));

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

        return [
            'damage_dealt' => $damage,
            'node_hp_remaining' => $newHp,
            'is_destroyed' => $newHp === 0,
            'stamina_remaining' => $newStamina,
            'loot' => null,
            'exp_gained' => 0,
            'new_player_exp' => $user->experience,
            'level_up' => false,
        ];
    }

    /**
     * @return array{
     *     loot: list<array{inventory_id: int, ore_id: int, name: string, quantity: int, rarity: string, base_sell_price: int}>,
     *     exp_gained: int,
     *     new_player_exp: int,
     *     level_up: bool,
     *     next_node: array{id: int, max_hp: int, current_hp: int, is_respawning: bool, respawns_at: null, node_type: array{slug: string, name: string, tier: int}}|null
     * }
     */
    public function collect(User $user, MiningNode $node): array
    {
        $node->loadMissing(['nodeType.oreTypes', 'island.nodeTypes']);

        if ($user->current_island_id !== null && $user->current_island_id !== $node->island_id) {
            abort(403, 'You can only collect from your current island node.');
        }

        if ($node->current_hp > 0) {
            abort(422, 'This node has not been destroyed yet.');
        }

        if ($node->respawns_at !== null) {
            abort(422, 'This node has already been collected.');
        }

        $equippedPickaxe = $this->getEquippedPickaxe($user);
        $loot = $this->rollNodeLoot($node, $user, $equippedPickaxe);

        $expGained = $node->nodeType->tier * 10;
        $newPlayerExp = $user->experience + $expGained;
        $previousLevel = $user->level;

        $user->experience = $newPlayerExp;
        $newLevel = $this->calculateLevel($newPlayerExp);
        $levelUp = false;

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

        $nextNode = $node->island ? $this->spawnRandomNodeForIsland($node->island, $node->nodeType) : null;

        return [
            'loot' => $loot,
            'exp_gained' => $expGained,
            'new_player_exp' => $newPlayerExp,
            'level_up' => $levelUp,
            'next_node' => $nextNode ? $this->serializeNode($nextNode) : null,
        ];
    }

    private function getEquippedPickaxe(User $user): ?Item
    {
        return $user->items()
            ->where('target_slot', 'pickaxe')
            ->where('equipped', true)
            ->latest('created_at')
            ->first()
            ?: null;
    }

    private function calculateCurrentStamina(PlayerStat $stats, ?Item $equippedPickaxe = null): float
    {
        if ($stats->stamina_last_updated_at === null) {
            return (float) $stats->stamina;
        }

        $elapsed = max(0, CarbonImmutable::now()->timestamp - $stats->stamina_last_updated_at->timestamp);
        $regenerated = $elapsed * $this->staminaRegenPerSecond($equippedPickaxe);

        return min(100.0, $stats->stamina + $regenerated);
    }

    private function staminaRegenPerSecond(?Item $equippedPickaxe = null): float
    {
        return self::BASE_STAMINA_REGEN_PER_SECOND + (float) ($equippedPickaxe?->stamina_regen_bonus ?? 0.0);
    }

    /**
     * @return list<array{inventory_id: int, ore_id: int, name: string, quantity: int, rarity: string, base_sell_price: int}>
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
                $quantity = random_int(3, 5);
                $inventory = $this->addToInventory($user->id, $ore, $quantity);
                $loot[] = [
                    'inventory_id' => $inventory->id,
                    'ore_id' => $ore->id,
                    'name' => $ore->name,
                    'quantity' => $quantity,
                    'rarity' => $ore->rarity,
                    'base_sell_price' => $ore->price,
                ];
            }
        }

        return $loot;
    }

    private function addToInventory(int $userId, OreType $ore, int $quantity): Inventory
    {
        $existing = Inventory::where('user_id', $userId)
            ->where('holdable_type', OreType::class)
            ->where('holdable_id', $ore->id)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return $existing->refresh();
        } else {
            return Inventory::create([
                'user_id' => $userId,
                'holdable_type' => OreType::class,
                'holdable_id' => $ore->id,
                'quantity' => $quantity,
            ]);
        }
    }

    private function spawnRandomNodeForIsland(Island $island, ?NodeType $fallbackNodeType = null): ?MiningNode
    {
        $randomNodeType = $island->nodeTypes()->inRandomOrder()->first() ?? $fallbackNodeType;

        if ($randomNodeType === null) {
            return null;
        }

        $newNode = MiningNode::create([
            'island_id' => $island->id,
            'node_type_id' => $randomNodeType->id,
            'max_hp' => $this->scaledNodeHp((int) $randomNodeType->base_hp),
            'current_hp' => $this->scaledNodeHp((int) $randomNodeType->base_hp),
            'respawns_at' => null,
        ]);

        $newNode->load('nodeType');
        broadcast(new NodeSpawned($newNode));

        return $newNode;
    }

    /**
     * @return array{id: int, max_hp: int, current_hp: int, is_respawning: bool, respawns_at: null, node_type: array{slug: string, name: string, tier: int}}
     */
    private function serializeNode(MiningNode $node): array
    {
        return [
            'id' => $node->id,
            'max_hp' => $node->max_hp,
            'current_hp' => $node->current_hp,
            'is_respawning' => false,
            'respawns_at' => null,
            'node_type' => [
                'slug' => $node->nodeType->slug,
                'name' => $node->nodeType->name,
                'tier' => $node->nodeType->tier,
            ],
        ];
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
                $scaledHp = $this->scaledNodeHp((int) $nodeType->base_hp);

                $newNode = MiningNode::create([
                    'island_id' => $island->id,
                    'node_type_id' => $nodeType->id,
                    'max_hp' => $scaledHp,
                    'current_hp' => $scaledHp,
                    'respawns_at' => null,
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

    private function scaledNodeHp(int $baseHp): int
    {
        return max(1, (int) round($baseHp * self::NODE_HP_SCALING_FACTOR));
    }
}
