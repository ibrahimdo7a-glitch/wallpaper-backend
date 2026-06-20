<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'name', 'position', 'type', 'html_code', 'image_file', 'link_url',
        'link_target', 'is_active', 'language', 'starts_at', 'ends_at',
        'sort_order', 'impressions_count', 'clicks_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
