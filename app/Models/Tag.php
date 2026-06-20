<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name_ar', 'name_en', 'slug', 'wallpapers_count'];

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
