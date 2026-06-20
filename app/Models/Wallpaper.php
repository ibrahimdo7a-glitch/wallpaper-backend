<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Wallpaper extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title_ar', 'title_en', 'slug', 'description_ar', 'description_en',
        'original_file', 'webp_file', 'thumbnail_file',
        'watermarked_file', 'watermarked_webp_file',
        'mobile_file', 'desktop_file', 'preview_file',
        'file_size', 'mime_type', 'width', 'height', 'resolution_label', 'image_hash',
        'device_type', 'status', 'rejection_reason',
        'uploaded_by', 'approved_by', 'category_id', 'watermark_id',
        'watermark_applied',
        'is_free', 'is_paid', 'price', 'currency', 'license_type',
        'premium_file', 'purchased_download_limit',
        'views_count', 'downloads_count', 'likes_count', 'sales_count',
        'is_featured', 'is_safe',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_paid' => 'boolean',
            'is_featured' => 'boolean',
            'is_safe' => 'boolean',
            'watermark_applied' => 'boolean',
            'published_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function watermark(): BelongsTo
    {
        return $this->belongsTo(Watermark::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'wallpaper_tag');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'wallpaper_category');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function getPublicImageUrlAttribute(): string
    {
        $file = $this->watermark_applied && $this->watermarked_webp_file
            ? $this->watermarked_webp_file
            : ($this->webp_file ?? $this->original_file);

        return Storage::disk('r2')->url($file);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_file) {
            return null;
        }

        return Storage::disk('r2')->url($this->thumbnail_file);
    }

    public function getDownloadUrlAttribute(): string
    {
        $file = $this->watermark_applied && $this->watermarked_file
            ? $this->watermarked_file
            : $this->original_file;

        return Storage::disk('r2')->temporaryUrl($file, now()->addMinutes(5));
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeForDevice($query, string $device)
    {
        return $query->where(function ($q) use ($device) {
            $q->where('device_type', $device)->orWhere('device_type', 'all');
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title_ar', 'title_en', 'status', 'category_id', 'watermark_id', 'is_featured'])
            ->logOnlyDirty()
            ->useLogName('wallpaper');
    }

    public function getResolutionLabel(): string
    {
        $mp = ($this->width * $this->height) / 1_000_000;

        if ($mp >= 33) {
            return '8K';
        } elseif ($mp >= 8) {
            return '4K';
        } elseif ($mp >= 3.7) {
            return 'QHD';
        } elseif ($mp >= 2) {
            return 'FHD';
        } elseif ($mp >= 0.9) {
            return 'HD';
        }

        return 'SD';
    }

    public function publish(User $approver): void
    {
        $this->update([
            'status' => 'published',
            'approved_by' => $approver->id,
            'published_at' => now(),
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }
}
