<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['player_id', 'name', 'target_slot', 'forge_grade', 'forge_signature', 'hp_bonus', 'attack_bonus', 'defense_bonus', 'mining_speed_bonus', 'mining_dmg_bonus', 'luck_bonus', 'stamina_regen_bonus', 'attack_speed_bonus', 'dodge_bonus', 'elemental_affinity', 'base_stats', 'final_stats', 'equipped'])]
class Item extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'base_stats' => 'array',
            'final_stats' => 'array',
            'equipped' => 'boolean',
            'stamina_regen_bonus' => 'float',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }
}
