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
        'is_active', 'is_featured', 'maintenance_mode', 'sort_order',
        'primary_color', 'accent_color',
        'telegram_url', 'whatsapp_url', 'channel_url',
        'download_cta_url', 'download_cta_label_ar', 'download_cta_label_en',
        'models_count', 'wallpapers_count', 'apps_count',
        'news_count', 'tutorials_count', 'total_downloads', 'total_views',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'is_featured'      => 'boolean',
            'maintenance_mode' => 'boolean',
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

    // ─── Relations ─────────────────────────────────────────────────────────────
    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class)->orderBy('sort_order');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(BrandSection::class)->orderBy('sort_order');
    }

    public function enabledSections(): HasMany
    {
        return $this->hasMany(BrandSection::class)
            ->where('is_enabled', true)
            ->orderBy('sort_order');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(ContentCollection::class)->orderBy('sort_order');
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

    // ─── Accessors ─────────────────────────────────────────────────────────────
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->logo);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->cover_image);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('maintenance_mode', false);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ─── Business Logic ────────────────────────────────────────────────────────
    public function refreshCounts(): void
    {
        $this->update([
            'models_count'     => $this->carModels()->where('is_active', true)->count(),
            'wallpapers_count' => $this->wallpapers()->where('status', 'published')->count(),
            'apps_count'       => $this->apps()->where('status', 'published')->count(),
            'news_count'       => $this->contentItems()->where('content_type', 'news')->where('status', 'published')->count(),
            'tutorials_count'  => $this->contentItems()->whereIn('content_type', ['tutorial_images', 'tutorial_videos'])->where('status', 'published')->count(),
            'total_downloads'  => $this->contentItems()->sum('downloads_count'),
            'total_views'      => $this->contentItems()->sum('views_count'),
        ]);
    }
}
