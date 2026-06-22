<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Tutorial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id', 'car_model_id', 'title_ar', 'title_en', 'slug',
        'summary_ar', 'summary_en', 'content_ar', 'content_en',
        'cover_image', 'video_url', 'difficulty', 'duration_label',
        'status', 'is_featured', 'views_count',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured'  => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tutorial) {
            if (empty($tutorial->slug)) {
                $base = $tutorial->title_en ?? $tutorial->title_ar ?? 'tutorial';
                $tutorial->slug = Str::slug($base) . '-' . Str::random(6);
            }
            if (empty($tutorial->published_at) && $tutorial->status === 'published') {
                $tutorial->published_at = now();
            }
        });

        static::saved(function (self $t) {
            $t->carModel?->refreshCounts();
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->cover_image);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
