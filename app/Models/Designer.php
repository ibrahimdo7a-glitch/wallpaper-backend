<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Designer extends Model
{
    protected $fillable = [
        'name_ar', 'name_en', 'avatar_path', 'bio_ar', 'telegram_url',
        'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) return null;
        if (filter_var($this->avatar_path, FILTER_VALIDATE_URL)) return $this->avatar_path;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->avatar_path);
    }

    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('sort_order'); }
}
