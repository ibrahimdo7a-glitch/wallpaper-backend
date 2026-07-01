<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'email', 'event', 'ip', 'country',
        'device', 'os', 'browser', 'user_agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];
}
