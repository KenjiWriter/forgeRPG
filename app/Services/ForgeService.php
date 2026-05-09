<?php

namespace App\Services;

use App\Models\ForgeSession;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\OreType;
use App\Models\User;

class ForgeService
{
    /**
     * Grade mapping: combined_score ranges to forge grades (1-10)
     * combined_score = (smelting * 0.30) + (smithing * 0.50) + (quench * 0.20)
     *
     * Grade I:   0-10    → factor 0.50
     * Grade II:  11-20   → factor 0.60
     * Grade III: 21-30   → factor 0.70
     * Grade IV:  31-40   → factor 0.80
     * Grade V:   41-55   → factor 0.90
     * Grade VI:  56-65   → factor 1.00
     * Grade VII: 66-75   → factor 1.15
     * Grade VIII:76-85   → factor 1.30
     * Grade IX:  86-95   → factor 1.50
     * Grade X:   96-100  → factor 2.00
     */
    private const GRADE_FACTORS = [
        1 => 0.50,
        2 => 0.60,
        3 => 0.70,
        4 => 0.80,
        5 => 0.90,
        6 => 1.00,
        7 => 1.15,
        8 => 1.30,
        9 => 1.50,
        10 => 2.00,
    ];

    /**
     * Validate ore inputs meet the Rule of 3 Ores requirement.
     *
     * @param  array  $ores  Array of { ore_type_id: int, quantity: int }
     * @return array [ valid: bool, error: string | null ]
     */
    public function validateOreInputs(array $ores): array
    {
        if (\count($ores) !== 3) {
            return [
                'valid' => false,
                'error' => 'Exactly 3 ores are required to forge an item (Rule of 3 Ores).',
            ];
        }

        foreach ($ores as $ore) {
            if (! isset($ore['ore_type_id'], $ore['quantity'])) {
                return [
                    'valid' => false,
                    'error' => 'Each ore must have ore_type_id and quantity.',
                ];
            }

            if (! is_int($ore['quantity']) || $ore['quantity'] < 1) {
                return [
                    'valid' => false,
                    'error' => 'Ore quantity must be a positive integer.',
                ];
            }

            $oreType = OreType::find($ore['ore_type_id']);
            if (! $oreType) {
                return [
                    'valid' => false,
                    'error' => "Ore type {$ore['ore_type_id']} does not exist.",
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate that the player owns sufficient quantities of the specified ores.
     *
     * @param  array  $ores  Array of { ore_type_id: int, quantity: int }
     * @return array [ valid: bool, error: string | null ]
     */
    public function validatePlayerOwnsOres(User $player, array $ores): array
    {
        foreach ($ores as $ore) {
            $inventory = Inventory::where('user_id', $player->id)
                ->where('holdable_type', OreType::class)
                ->where('holdable_id', $ore['ore_type_id'])
                ->first();

            $owned = $inventory?->quantity ?? 0;

            if ($owned < $ore['quantity']) {
                $oreType = OreType::find($ore['ore_type_id']);

                return [
                    'valid' => false,
                    'error' => "Insufficient {$oreType->name}. You have {$owned} but need {$ore['quantity']}.",
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Generate a deterministic Forge Signature from ore inputs.
     *
     * Format: sorted ore entries by ore_type_id, joined as ID:QTY|ID:QTY
     *
     * @param  array  $ores  Array of { ore_type_id: int, quantity: int }
     * @return string Raw sorted signature string
     */
    public function generateSignature(array $ores): string
    {
        // Sort by ore_type_id ascending
        usort($ores, fn ($a, $b) => $a['ore_type_id'] <=> $b['ore_type_id']);

        // Build ID:QTY pairs
        $pairs = array_map(
            fn ($ore) => "{$ore['ore_type_id']}:{$ore['quantity']}",
            $ores
        );

        return implode('|', $pairs);
    }

    /**
     * Map a combined score (0-100) to a forge grade (1-10).
     *
     * @param  int  $combinedScore  0-100
     * @return int Grade 1-10
     */
    public function mapScoreToGrade(int $combinedScore): int
    {
        return match (true) {
            $combinedScore <= 10 => 1,
            $combinedScore <= 20 => 2,
            $combinedScore <= 30 => 3,
            $combinedScore <= 40 => 4,
            $combinedScore <= 55 => 5,
            $combinedScore <= 65 => 6,
            $combinedScore <= 75 => 7,
            $combinedScore <= 85 => 8,
            $combinedScore <= 95 => 9,
            default => 10,
        };
    }

    /**
     * Get the grade factor (stat multiplier) for a given grade.
     *
     * @param  int  $grade  1-10
     * @return float Multiplier
     */
    public function getGradeFactor(int $grade): float
    {
        return self::GRADE_FACTORS[$grade] ?? 1.00;
    }

    /**
     * Consume ore from player inventory (decrement quantities or delete rows).
     *
     * @param  array  $ores  Array of { ore_type_id: int, quantity: int }
     */
    public function consumeOres(User $player, array $ores): void
    {
        foreach ($ores as $ore) {
            $inventory = Inventory::where('user_id', $player->id)
                ->where('holdable_type', OreType::class)
                ->where('holdable_id', $ore['ore_type_id'])
                ->firstOrFail();

            $inventory->quantity -= $ore['quantity'];

            if ($inventory->quantity <= 0) {
                $inventory->delete();
            } else {
                $inventory->save();
            }
        }
    }

    /**
     * Calculate item base stats from ore inputs.
     *
     * Base Stat = SUM of (ore_type.base_{stat} * quantity_weight)
     * where quantity_weight = quantity_bucket / 5 (normalized 0.2–1.0)
     *
     * For simplicity in this phase, we use actual quantity normalized by the max quantity in the set,
     * or a fixed normalization factor.
     *
     * @param  array  $ores  Array of { ore_type_id: int, quantity: int }
     * @return array Keys: hp, attack, defense, mining_speed, attack_speed, dodge (0-capped)
     */
    public function calculateBaseStats(array $ores): array
    {
        $baseStats = [
            'hp' => 0,
            'attack' => 0,
            'defense' => 0,
            'mining_speed' => 0,
            'attack_speed' => 0,
            'dodge' => 0,
        ];

        foreach ($ores as $ore) {
            $oreType = OreType::find($ore['ore_type_id']);

            if (! $oreType) {
                continue;
            }

            // Normalize quantity: scale 1-50 to weight 0.2-1.0
            // Simple approach: min(quantity / 50, 1.0)
            $quantityWeight = min($ore['quantity'] / 50.0, 1.0);

            $baseStats['hp'] += (int) round(($oreType->base_hp ?? 0) * $quantityWeight);
            $baseStats['attack'] += (int) round(($oreType->base_attack ?? 0) * $quantityWeight);
            $baseStats['defense'] += (int) round(($oreType->base_defense ?? 0) * $quantityWeight);
        }

        // Cap dodge at 0-100
        $baseStats['dodge'] = min(100, $baseStats['dodge']);

        return $baseStats;
    }

    /**
     * Apply grade factor and level multiplier to base stats to produce final stats.
     *
     * Final Stat = ROUND(Base Stat * Grade Factor * Level Multiplier)
     * Level Multiplier = 1 + (level - 1) * 0.02 (capped at 2.0 at level 50)
     *
     * @param  array  $baseStats  Base stats from calculateBaseStats
     * @param  int  $grade  Forge grade 1-10
     * @return array Final stats
     */
    public function applyStatMultipliers(array $baseStats, int $playerLevel, int $grade): array
    {
        $gradeFactor = $this->getGradeFactor($grade);
        $levelMultiplier = 1.0 + ($playerLevel - 1) * 0.02;
        $levelMultiplier = min($levelMultiplier, 2.0); // Cap at level 50

        $finalStats = [];
        foreach ($baseStats as $stat => $value) {
            $finalStats[$stat] = (int) round($value * $gradeFactor * $levelMultiplier);
        }

        return $finalStats;
    }

    /**
     * Create a forge session in progress.
     */
    public function createForgeSession(
        User $player,
        string $targetSlot,
        array $ores,
        ?int $forgeRuneId = null
    ): ForgeSession {
        return ForgeSession::create([
            'player_id' => $player->id,
            'target_slot' => $targetSlot,
            'ore_inputs' => $ores,
            'forge_rune_id' => $forgeRuneId,
            'smelting_score' => 0,
            'smithing_score' => 0,
            'quench_score' => 0,
            'combined_score' => 0,
            'forge_grade' => 0,
            'result_item_id' => null,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Complete a forge session: compute combined score, grade, stats, and create item.
     *
     * @param  int  $smeltingScore  0-100
     * @param  int  $smithingScore  0-100
     * @param  int  $quenchScore  0-100
     * @return array [ item: Item, grade: int, combined_score: int ]
     */
    public function completeForge(
        ForgeSession $session,
        int $smeltingScore,
        int $smithingScore,
        int $quenchScore,
        string $itemName
    ): array {
        // Compute combined score: (smelting * 0.30) + (smithing * 0.50) + (quench * 0.20)
        $combinedScore = (int) round(
            ($smeltingScore * 0.30) +
            ($smithingScore * 0.50) +
            ($quenchScore * 0.20)
        );

        $grade = $this->mapScoreToGrade($combinedScore);
        $signature = $this->generateSignature($session->ore_inputs);

        // Load player to get level
        $player = $session->player;

        // Calculate base stats from ores
        $baseStats = $this->calculateBaseStats($session->ore_inputs);

        // Apply multipliers
        $finalStats = $this->applyStatMultipliers($baseStats, $player->level, $grade);

        // Create item
        $item = Item::create([
            'player_id' => $player->id,
            'name' => $itemName,
            'target_slot' => $session->target_slot,
            'forge_grade' => $grade,
            'forge_signature' => $signature,
            'hp_bonus' => $finalStats['hp'] ?? 0,
            'attack_bonus' => $finalStats['attack'] ?? 0,
            'defense_bonus' => $finalStats['defense'] ?? 0,
            'mining_speed_bonus' => $finalStats['mining_speed'] ?? 0,
            'attack_speed_bonus' => $finalStats['attack_speed'] ?? 0,
            'dodge_bonus' => $finalStats['dodge'] ?? 0,
            'base_stats' => $baseStats,
            'final_stats' => $finalStats,
            'equipped' => false,
            'created_at' => now(),
        ]);

        // Update forge session
        $session->update([
            'smelting_score' => $smeltingScore,
            'smithing_score' => $smithingScore,
            'quench_score' => $quenchScore,
            'combined_score' => $combinedScore,
            'forge_grade' => $grade,
            'result_item_id' => $item->id,
            'status' => 'completed',
        ]);

        return [
            'item' => $item,
            'grade' => $grade,
            'combined_score' => $combinedScore,
        ];
    }
}
