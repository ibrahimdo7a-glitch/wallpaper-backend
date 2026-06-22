<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarFile extends Model
{
    use SoftDeletes;

    protected $table = 'car_files';

    protected $fillable = [
        'brand_id', 'car_model_id', 'title_ar', 'title_en', 'slug',
        'description_ar', 'description_en', 'file_path', 'file_type',
        'mime_type', 'file_size', 'version', 'status',
        'is_featured', 'downloads_count', 'published_at',
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
        static::creating(function (self $file) {
            if (empty($file->slug)) {
                $base = $file->title_en ?? $file->title_ar ?? 'file';
                $file->slug = Str::slug($base) . '-' . Str::random(6);
            }
        });
    }

    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function carModel(): BelongsTo { return $this->belongsTo(CarModel::class, 'car_model_id'); }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->file_path);
    }

    public function getFileSizeLabelAttribute(): string
    {
        if (! $this->file_size) return '—';
        $mb = $this->file_size / 1024 / 1024;
        return $mb >= 1 ? round($mb, 1) . ' MB' : round($this->file_size / 1024) . ' KB';
    }

    public function scopePublished($query) { return $query->where('status', 'published'); }
}
