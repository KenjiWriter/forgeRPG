<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['level', 'exp_required', 'unlock_note'])]
class LevelDefinition extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'level';

    protected $keyType = 'int';
}
