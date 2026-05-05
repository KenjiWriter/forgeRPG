<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'hp', 'attack', 'defense', 'mining_speed', 'attack_speed', 'dodge', 'stamina', 'stamina_last_updated_at'])]
class PlayerStat extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'stamina_last_updated_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
