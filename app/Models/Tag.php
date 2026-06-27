<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name_ar', 'name_en', 'slug', 'wallpapers_count'];

    protected static function booted(): void
    {
        static::creating(function (self $t) {
            if (empty($t->slug)) {
                $latin = Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', (string) ($t->name_en ?? $t->name_ar ?? 'tag')));
                $t->slug = ($latin ?: 'tag') . '-' . Str::lower(Str::random(4));
            }
        });
    }

    public function wallpapers(): BelongsToMany
    {
        return $this->belongsToMany(Wallpaper::class, 'wallpaper_tag');
    }

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();

        return $locale === 'ar' ? ($this->name_ar ?: $this->name_en) : ($this->name_en ?: $this->name_ar);
    }
}
