<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsVisitor extends Model
{
    protected $table = 'analytics_visitors';
    protected $primaryKey = 'visitor_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'visitor_id', 'member_id', 'first_seen_at', 'last_seen_at',
        'country', 'city', 'device', 'os', 'browser', 'ip',
        'last_path', 'source', 'total_views', 'sessions',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
