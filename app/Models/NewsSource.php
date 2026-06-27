<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsSource extends Model
{
    protected $table = 'news_sources';

    protected $fillable = [
        'name', 'url', 'is_active', 'sort_order', 'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
