<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'price', 'power', 'luck_boost', 'speed_modifier', 'slots', 'requires_island_id'])]
class Pickaxe extends Model
{
    public function requiredIsland(): BelongsTo
    {
        return $this->belongsTo(Island::class, 'requires_island_id');
    }
}
