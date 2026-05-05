<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'rarity', 'base_chance', 'multiplier', 'price', 'elemental_affinity', 'base_attack', 'base_defense', 'base_hp'])]
class OreType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
        ];
    }

    public function nodeTypes(): BelongsToMany
    {
        return $this->belongsToMany(NodeType::class, 'node_type_ore_sources');
    }
}
