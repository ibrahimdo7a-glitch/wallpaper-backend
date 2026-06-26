<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberLoginToken extends Model
{
    protected $fillable = ['token', 'member_id', 'telegram_id', 'status', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
