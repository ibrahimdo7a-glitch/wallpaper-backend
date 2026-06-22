<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id', 'brand_section_id', 'content_collection_id', 'car_model_id', 'content_type',
        'title_ar', 'title_en', 'slug',
        'description_ar', 'description_en',
        'image_path', 'thumbnail_path', 'file_path', 'video_url', 'external_url',
        'metadata', 'status', 'is_featured', 'is_pinned',
        'sort_order', 'views_count', 'downloads_count', 'published_at',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'is_featured'  => 'boolean',
        'is_pinned'    => 'boolean',
        'published_at' => 'datetime',
        'views_count'  => 'integer',
        'downloads_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->slug)) {
                $base = Str::slug($item->title_ar) ?: Str::slug($item->title_en ?? '') ?: Str::random(8);
                $item->slug = $base . '-' . Str::random(4);
            }
            if (empty($item->published_at) && $item->status === 'published') {
                $item->published_at = now();
            }
        });
    }

    public function brand(): BelongsTo        { return $this->belongsTo(Brand::class); }
    public function brandSection(): BelongsTo { return $this->belongsTo(BrandSection::class); }
    public function collection(): BelongsTo   { return $this->belongsTo(ContentCollection::class, 'content_collection_id'); }
    public function carModel(): BelongsTo     { return $this->belongsTo(CarModel::class); }

    // ─── URL Accessors ─────────────────────────────────────────────────────────
    private function storageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
        return Storage::disk(config('filesystems.default', 'public'))->url($path);
    }

    public function getImageUrlAttribute(): ?string     { return $this->storageUrl($this->image_path); }
    public function getThumbnailUrlAttribute(): ?string { return $this->storageUrl($this->thumbnail_path); }
    public function getFileUrlAttribute(): ?string      { return $this->storageUrl($this->file_path); }

    public function getFileSizeLabelAttribute(): string
    {
        $size = $this->metadata['file_size'] ?? 0;
        if (!$size) return '—';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($size >= 1024 && $i < 3) { $size /= 1024; $i++; }
        return round($size, 1) . ' ' . $units[$i];
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────
    public function scopePublished($q)  { return $q->where('status', 'published'); }
    public function scopeFeatured($q)   { return $q->where('is_featured', true); }
    public function scopePinned($q)     { return $q->where('is_pinned', true)->orderBy('sort_order'); }
    public function scopeForBrand($q, int $brandId) { return $q->where('brand_id', $brandId); }
    public function scopeForModel($q, int $modelId) { return $q->where('car_model_id', $modelId); }
    public function scopeForCollection($q, int $collectionId) { return $q->where('content_collection_id', $collectionId); }
    public function scopeOfType($q, string $type)   { return $q->where('content_type', $type); }
}
