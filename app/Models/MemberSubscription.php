<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's opt-in to market alerts. One row per (member, channel[, brand]).
 * channel: 'cars' | 'parts'.  brand_id null = every brand in that channel.
 * News alerts live on Member.news_telegram (delivery predates this table).
 */
class MemberSubscription extends Model
{
    protected $fillable = ['member_id', 'channel', 'brand_id'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
