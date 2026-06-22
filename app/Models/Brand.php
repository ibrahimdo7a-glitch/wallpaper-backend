<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = [
        'name_ar', 'name_en', 'slug', 'logo', 'cover_image',
        'description_ar', 'description_en', 'country', 'website_url',
        'is_active', 'is_featured', 'sort_order',
        'models_count', 'wallpapers_count', 'apps_count',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name_en ?? $brand->name_ar) ?: Str::random(8);
            }
        });
    }

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class)->orderBy('sort_order');
    }

    public function wallpapers(): HasMany
    {
        return $this->hasMany(Wallpaper::class);
    }

    public function apps(): HasMany
    {
        return $this->hasMany(AndroidApp::class, 'brand_id');
    }

    public function tutorials(): HasMany
    {
        return $this->hasMany(Tutorial::class);
    }

    public function newsArticles()
    {
        return $this->belongsToMany(NewsArticle::class, 'news_article_brand');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->logo);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->cover_image);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function refreshCounts(): void
    {
        $this->update([
            'models_count'    => $this->carModels()->where('is_active', true)->count(),
            'wallpapers_count'=> $this->wallpapers()->where('status', 'published')->count(),
            'apps_count'      => $this->apps()->where('status', 'published')->count(),
        ]);
    }
}
