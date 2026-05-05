<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ore_inputs', 'forge_rune_id', 'smelting_score', 'smithing_score', 'quench_score', 'combined_score', 'forge_grade', 'result_item_id'])]
class ForgeSession extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ore_inputs' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
