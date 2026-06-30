<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false; // only created_at, set explicitly

    protected $fillable = [
        'visitor_id', 'session_id', 'member_id', 'type', 'name',
        'path', 'referrer_host', 'source', 'country', 'device', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];
}
