<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['island_id', 'node_type_id', 'max_hp', 'current_hp', 'respawns_at'])]
class MiningNode extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'respawns_at' => 'datetime',
        ];
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    public function nodeType(): BelongsTo
    {
        return $this->belongsTo(NodeType::class);
    }

    public function isDestroyed(): bool
    {
        return $this->current_hp === 0;
    }

    public function isRespawning(): bool
    {
        return $this->respawns_at !== null && $this->respawns_at->isFuture();
    }
}
