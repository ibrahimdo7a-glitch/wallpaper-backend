<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentCollection extends Model
{
    protected $fillable = [
        'brand_id', 'brand_section_id', 'car_model_id', 'name_ar', 'name_en', 'slug',
        'description_ar', 'description_en', 'image_path', 'icon',
        'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (empty($c->slug)) {
                $base = Str::slug($c->name_en ?: $c->name_ar) ?: Str::random(6);
                // ensure uniqueness within the brand
                $slug = $base;
                $i = 1;
                while (static::where('brand_id', $c->brand_id)->where('slug', $slug)->exists()) {
                    $slug = $base . '-' . (++$i);
                }
                $c->slug = $slug;
            }
        });
    }

    public function brand(): BelongsTo        { return $this->belongsTo(Brand::class); }
    public function brandSection(): BelongsTo { return $this->belongsTo(BrandSection::class); }
    public function carModel(): BelongsTo     { return $this->belongsTo(CarModel::class); }
    public function contentItems(): HasMany   { return $this->hasMany(ContentItem::class, 'content_collection_id'); }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) return $this->image_path;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->image_path);
    }

    public function scopeActive($q)  { return $q->where('is_active', true)->orderBy('sort_order'); }
}
