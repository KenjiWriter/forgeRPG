<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'tier', 'base_hp', 'respawn_minutes'])]
class NodeType extends Model
{
    public function oreTypes(): BelongsToMany
    {
        return $this->belongsToMany(OreType::class, 'node_type_ore_sources');
    }

    public function islands(): BelongsToMany
    {
        return $this->belongsToMany(Island::class, 'location_node_types');
    }

    public function miningNodes(): HasMany
    {
        return $this->hasMany(MiningNode::class);
    }
}
