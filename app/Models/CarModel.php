<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarModel extends Model
{
    protected $table = 'car_models';

    protected $fillable = [
        'brand_id', 'name_ar', 'name_en', 'slug',
        'image', 'cover_image', 'description_ar', 'description_en',
        'year_from', 'year_to', 'car_type', 'fuel_type',
        'is_active', 'is_featured', 'sort_order',
        'wallpapers_count', 'apps_count', 'tutorials_count',
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
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $base = $model->name_en ?? $model->name_ar;
                $model->slug = Str::slug($base) . '-' . Str::random(4);
            }
        });

        static::saved(function (self $model) {
            $model->brand?->refreshCounts();
            $model->flushBrandCache();
        });

        static::deleted(function (self $model) {
            $model->brand?->refreshCounts();
            $model->flushBrandCache();
        });
    }

    /** Forget cached brand payloads so model edits (image, name, …) appear immediately. */
    public function flushBrandCache(): void
    {
        $slug = $this->brand?->slug;
        if (! $slug) return;

        Cache::forget("brand.{$slug}");
        Cache::forget("brand.{$slug}.sections");
        Cache::forget("brand.{$slug}.models");
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function wallpapers(): HasMany
    {
        return $this->hasMany(Wallpaper::class, 'car_model_id');
    }

    public function apps(): HasMany
    {
        return $this->hasMany(AndroidApp::class, 'car_model_id');
    }

    public function importantApps(): BelongsToMany
    {
        return $this->belongsToMany(AndroidApp::class, 'car_model_important_apps', 'car_model_id', 'app_id')
                    ->withPivot('sort_order')
                    ->orderBy('car_model_important_apps.sort_order');
    }

    public function tutorials(): HasMany
    {
        return $this->hasMany(Tutorial::class, 'car_model_id');
    }

    public function carFiles(): HasMany
    {
        return $this->hasMany(CarFile::class, 'car_model_id');
    }

    // ─── Per-type content scoped to this model (admin tabs) ─────────────────────
    public function wallpaperContent(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'car_model_id')->where('content_type', 'wallpapers');
    }

    public function tutorialContent(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'car_model_id')->whereIn('content_type', ['tutorial_images', 'tutorial_videos', 'tutorials']);
    }

    public function fileContent(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'car_model_id')->whereIn('content_type', ['files', 'manuals']);
    }

    /** Sub-sections (collections) that belong to this model. */
    public function collections(): HasMany
    {
        return $this->hasMany(ContentCollection::class, 'car_model_id')->orderBy('sort_order');
    }

    /** Sections this model chooses to show (empty = show all model-specific). */
    public function visibleSections(): BelongsToMany
    {
        return $this->belongsToMany(BrandSection::class, 'car_model_section');
    }

    public function newsArticles(): BelongsToMany
    {
        return $this->belongsToMany(NewsArticle::class, 'news_article_car_model');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->image);
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

    public function getYearLabelAttribute(): string
    {
        if ($this->year_from && $this->year_to) return "{$this->year_from} - {$this->year_to}";
        if ($this->year_from) return "{$this->year_from}+";
        return '';
    }

    public function refreshCounts(): void
    {
        $this->update([
            'wallpapers_count' => $this->wallpapers()->where('status', 'published')->count(),
            'apps_count'       => $this->apps()->where('status', 'published')->count(),
            'tutorials_count'  => $this->tutorials()->where('status', 'published')->count(),
        ]);
    }
}
