<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketCategory extends Model
{
    protected $fillable = ['listing_type', 'name_ar', 'name_en', 'slug', 'icon', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        // Slug is auto-generated and hidden from the admin: a short latinized
        // base + a random token (lightly obfuscated, always unique).
        static::creating(function (self $cat) {
            if (empty($cat->slug)) {
                $base = Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', (string) ($cat->name_en ?: $cat->name_ar)));
                $cat->slug = ($base ?: 'section') . '-' . Str::lower(Str::random(4));
            }
        });
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    public function listings()
    {
        return $this->hasMany(MarketListing::class);
    }

    public function fields()
    {
        return $this->hasMany(MarketField::class)->orderBy('sort_order');
    }
}
