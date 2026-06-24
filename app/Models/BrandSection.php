<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BrandSection extends Model
{
    protected $fillable = [
        'brand_id', 'section_type_id', 'slug',
        'custom_name_ar', 'custom_name_en',
        'custom_description_ar', 'custom_description_en',
        'icon', 'cover_image',
        'is_enabled', 'show_in_brand_home', 'show_in_navigation',
        'show_in_homepage', 'is_model_specific',
        'layout_type', 'sort_order', 'settings',
    ];

    protected $casts = [
        'is_enabled'         => 'boolean',
        'show_in_brand_home' => 'boolean',
        'show_in_navigation' => 'boolean',
        'show_in_homepage'   => 'boolean',
        'is_model_specific'  => 'boolean',
        'settings'           => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $section) {
            if (empty($section->slug) && $section->section_type_id) {
                $section->slug = $section->sectionType?->key ?? 'section-' . $section->section_type_id;
            }
        });

        // Bust the brand caches so toggles (enable/nav/home) show up immediately.
        static::saved(fn (self $section) => $section->flushBrandCache());
        static::deleted(fn (self $section) => $section->flushBrandCache());
    }

    /** Forget all cached payloads that depend on this brand's sections. */
    public function flushBrandCache(): void
    {
        $slug = $this->brand?->slug;
        if (! $slug) return;

        Cache::forget("brand.{$slug}");
        Cache::forget("brand.{$slug}.sections");
        Cache::forget("brand.{$slug}.models");
    }

    public function brand(): BelongsTo      { return $this->belongsTo(Brand::class); }
    public function sectionType(): BelongsTo { return $this->belongsTo(SectionType::class); }
    public function contentItems(): HasMany  { return $this->hasMany(ContentItem::class); }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->cover_image);
    }

    /** Resolved name: custom override or falls back to section_type name */
    public function getNameAr(): string
    {
        return $this->custom_name_ar ?? $this->sectionType?->name_ar ?? $this->slug;
    }

    public function getNameEn(): string
    {
        return $this->custom_name_en ?? $this->sectionType?->name_en ?? $this->slug;
    }

    public function getIcon(): string
    {
        return $this->icon ?? $this->sectionType?->default_icon ?? '📁';
    }

    public function scopeEnabled($q)     { return $q->where('is_enabled', true); }
    public function scopeInNavigation($q){ return $q->where('show_in_navigation', true); }
    public function scopeInHome($q)      { return $q->where('show_in_brand_home', true); }
}
