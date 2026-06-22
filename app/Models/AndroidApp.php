<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AndroidApp extends Model
{
    use SoftDeletes;

    protected $table = 'apps';

    protected $fillable = [
        'app_category_id', 'title_ar', 'title_en', 'slug',
        'description_ar', 'description_en',
        'icon_file', 'apk_file', 'external_url',
        'version', 'package_name', 'developer',
        'min_android', 'file_size', 'status',
        'downloads_count', 'is_featured', 'is_free',
        'published_at',
        'meta_title_ar', 'meta_title_en',
        'meta_description_ar', 'meta_description_en',
    ];

    protected function casts(): array
    {
        return [
            'is_featured'    => 'boolean',
            'is_free'        => 'boolean',
            'published_at'   => 'datetime',
            'downloads_count'=> 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $app) {
            if (empty($app->slug)) {
                $base = $app->title_en ?? $app->title_ar ?? 'app';
                $slug = Str::slug($base) ?: 'app';
                $app->slug = $slug . '-' . Str::random(6);
            }
            if (empty($app->published_at) && $app->status === 'published') {
                $app->published_at = now();
            }
        });

        static::saved(function (self $app) {
            // Update category apps_count
            if ($app->app_category_id) {
                AppCategory::where('id', $app->app_category_id)
                    ->update(['apps_count' => static::where('app_category_id', $app->app_category_id)->where('status', 'published')->count()]);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AppCategory::class, 'app_category_id');
    }

    public function installationSteps(): HasMany
    {
        return $this->hasMany(AppInstallationStep::class, 'app_id')->orderBy('step_number');
    }

    public function getIconUrlAttribute(): ?string
    {
        if (! $this->icon_file) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->icon_file);
    }

    public function getApkUrlAttribute(): ?string
    {
        if ($this->apk_file) {
            return Storage::disk(config('filesystems.default', 'public'))->url($this->apk_file);
        }
        return $this->external_url;
    }

    public function getFileSizeLabelAttribute(): string
    {
        if (! $this->file_size) return '—';
        $mb = $this->file_size / 1024 / 1024;
        return $mb >= 1 ? round($mb, 1) . ' MB' : round($this->file_size / 1024, 0) . ' KB';
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
