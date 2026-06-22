<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsCategory extends Model
{
    protected $table = 'news_categories';

    protected $fillable = [
        'name_ar', 'name_en', 'slug', 'color', 'icon',
        'is_active', 'sort_order', 'articles_count',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $cat) {
            if (empty($cat->slug)) {
                $cat->slug = Str::slug($cat->name_en ?? $cat->name_ar) ?: Str::random(6);
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'news_category_id');
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
