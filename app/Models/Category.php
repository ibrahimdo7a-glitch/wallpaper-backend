<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'name_ar', 'name_en', 'slug',
        'description_ar', 'description_en',
        'cover_image', 'icon',
        'is_active', 'sort_order',
        'meta_title_ar', 'meta_title_en',
        'meta_description_ar', 'meta_description_en',
        'wallpapers_count', 'downloads_count', 'likes_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function wallpapers(): HasMany
    {
        return $this->hasMany(Wallpaper::class);
    }

    public function wallpapersMany(): BelongsToMany
    {
        return $this->belongsToMany(Wallpaper::class, 'wallpaper_category');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return Storage::disk(config('filesystems.default', 'public'))->url($this->cover_image);
    }

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();

        return $locale === 'ar' ? ($this->name_ar ?: $this->name_en) : ($this->name_en ?: $this->name_ar);
    }

    protected static function booted(): void
    {
        $onChange = function () {
            \Illuminate\Support\Facades\Cache::forget('categories.tree');
            static::pingFrontend();
        };
        static::created($onChange);
        static::updated($onChange);
        static::deleted($onChange);
        static::restored($onChange);
    }

    protected static function pingFrontend(): void
    {
        try {
            $url = rtrim(config('app.frontend_url', ''), '/') . '/api/revalidate';
            $token = config('app.revalidate_token', '');
            if (! $token) return;
            \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders(['x-revalidate-token' => $token])
                ->post($url);
        } catch (\Throwable) {}
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $category = $this;

        while ($category) {
            array_unshift($breadcrumbs, [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'slug' => $category->slug,
            ]);
            $category = $category->parent;
        }

        return $breadcrumbs;
    }
}
