<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slot_type', 'forge_grade', 'forge_signature', 'hp_bonus', 'attack_bonus', 'defense_bonus', 'mining_speed_bonus', 'mining_dmg_bonus', 'luck_bonus', 'attack_speed_bonus', 'dodge_bonus', 'elemental_affinity'])]
class Item extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
