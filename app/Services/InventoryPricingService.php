<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Item;
use App\Models\OreType;

class InventoryPricingService
{
    public function resolveInventoryUnitPrice(Inventory $inventory): int
    {
        $holdable = $inventory->holdable;

        if ($holdable instanceof OreType) {
            return max(1, (int) $holdable->price);
        }

        if ($holdable instanceof Item) {
            return $this->calculateForgedItemUnitPrice($holdable);
        }

        return 1;
    }

    public function calculateForgedItemUnitPrice(Item $item): int
    {
        $grade = max(1, min(10, (int) $item->forge_grade));

        $rarityMultiplier = match ($this->rarityFromGrade($grade)) {
            'mythical' => 5.20,
            'legendary' => 3.70,
            'epic' => 2.60,
            'rare' => 1.85,
            'uncommon' => 1.35,
            default => 1.00,
        };

        $gradeMultiplier = 1 + (($grade - 1) * 0.12);
        $statScore =
            ((int) $item->attack_bonus * 4)
            + ((int) $item->defense_bonus * 3)
            + ((int) $item->hp_bonus)
            + ((int) $item->mining_dmg_bonus * 3)
            + ((int) $item->mining_speed_bonus * 2)
            + ((int) $item->luck_bonus * 5)
            + ((int) $item->attack_speed_bonus * 2)
            + ((int) $item->dodge_bonus * 4);

        $base = 50 + max(0, $statScore * 2);

        return (int) max(10, round($base * $rarityMultiplier * $gradeMultiplier));
    }

    private function rarityFromGrade(int $grade): string
    {
        return match (true) {
            $grade >= 10 => 'mythical',
            $grade >= 9 => 'legendary',
            $grade >= 7 => 'epic',
            $grade >= 5 => 'rare',
            $grade >= 3 => 'uncommon',
            default => 'common',
        };
    }
}
