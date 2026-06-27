<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppCategory extends Model
{
    protected $fillable = [
        'name_ar', 'name_en', 'slug',
        'icon', 'cover_image',
        'is_active', 'sort_order', 'apps_count',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->slug = $m->slug ?: self::makeSlug($m->name_en ?? $m->name_ar));
    }

    public static function makeSlug(?string $base): string
    {
        $latin = Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', (string) ($base ?: 'item')));
        return ($latin ?: 'item') . '-' . Str::lower(Str::random(4));
    }

    public function apps(): HasMany
    {
        return $this->hasMany(AndroidApp::class, 'app_category_id');
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
}
