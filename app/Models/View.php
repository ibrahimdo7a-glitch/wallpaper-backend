<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class View extends Model
{
    public $timestamps = false;

    protected $fillable = ['wallpaper_id', 'ip_hash', 'user_agent_hash', 'country_code', 'created_at'];

    public function wallpaper(): BelongsTo
    {
        return $this->belongsTo(Wallpaper::class);
    }
}
