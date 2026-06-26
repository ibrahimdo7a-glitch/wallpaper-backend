<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'telegram_id', 'telegram_username', 'name', 'photo_url', 'phone',
        'status', 'tier', 'is_premium', 'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'is_premium'    => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    public function listings()
    {
        return $this->hasMany(MarketListing::class);
    }

    /** Safe public representation returned to the frontend. */
    public function toPublicArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'username'  => $this->telegram_username,
            'photo_url' => $this->photo_url,
            'tier'      => $this->tier,
            'is_premium'=> (bool) $this->is_premium,
        ];
    }
}
