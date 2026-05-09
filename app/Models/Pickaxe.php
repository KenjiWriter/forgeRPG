<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'rarity', 'price', 'power', 'luck_boost', 'stamina_regen_bonus', 'speed_modifier', 'slots', 'requires_island_id'])]
class Pickaxe extends Model
{
    protected function casts(): array
    {
        return [
            'stamina_regen_bonus' => 'float',
        ];
    }

    public function requiredIsland(): BelongsTo
    {
        return $this->belongsTo(Island::class, 'requires_island_id');
    }

    public function userItems(): HasMany
    {
        return $this->hasMany(UserItem::class);
    }
}
