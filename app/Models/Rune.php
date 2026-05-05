<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'effect', 'description'])]
class Rune extends Model
{
    protected function casts(): array
    {
        return [
            'effect' => 'array',
        ];
    }
}
