<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['player_id', 'target_slot', 'ore_inputs', 'forge_rune_id', 'smelting_score', 'smithing_score', 'quench_score', 'combined_score', 'forge_grade', 'result_item_id', 'status'])]
class ForgeSession extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ore_inputs' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function forgeRune(): BelongsTo
    {
        return $this->belongsTo(Rune::class, 'forge_rune_id');
    }

    public function resultItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'result_item_id');
    }
}
