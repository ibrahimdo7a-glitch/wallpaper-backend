<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketCategory extends Model
{
    protected $fillable = ['listing_type', 'name_ar', 'name_en', 'slug', 'icon', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    public function listings()
    {
        return $this->hasMany(MarketListing::class);
    }
}
