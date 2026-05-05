<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'min_level', 'unlock_condition'])]
class Island extends Model
{
    protected function casts(): array
    {
        return [
            'unlock_condition' => 'array',
        ];
    }

    public function nodeTypes(): BelongsToMany
    {
        return $this->belongsToMany(NodeType::class, 'location_node_types');
    }

    public function miningNodes(): HasMany
    {
        return $this->hasMany(MiningNode::class);
    }

    public function enemies(): HasMany
    {
        return $this->hasMany(Enemy::class);
    }
}
