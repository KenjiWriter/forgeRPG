<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'pickaxe_id', 'quantity'])]
class UserItem extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pickaxe(): BelongsTo
    {
        return $this->belongsTo(Pickaxe::class);
    }
}
