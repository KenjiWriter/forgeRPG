<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PlayerStat::class);
    }

    public function equipmentSlots(): HasMany
    {
        return $this->hasMany(EquipmentSlot::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'player_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function forgeSessions(): HasMany
    {
        return $this->hasMany(ForgeSession::class, 'player_id');
    }

    public function currentIsland(): BelongsTo
    {
        return $this->belongsTo(Island::class, 'current_island_id');
    }
}
