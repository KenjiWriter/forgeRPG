<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['island_id', 'name', 'hp', 'attack', 'defense', 'attack_speed', 'elemental_affinity', 'drop_table'])]
class Enemy extends Model
{
    protected function casts(): array
    {
        return [
            'drop_table' => 'array',
        ];
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }
}
