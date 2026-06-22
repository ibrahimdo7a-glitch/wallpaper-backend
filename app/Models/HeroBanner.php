<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HeroBanner extends Model
{
    protected $fillable = [
        'title_ar', 'title_en', 'subtitle_ar', 'subtitle_en',
        'description_ar', 'description_en', 'image_path',
        'bg_color', 'text_color',
        'primary_btn_label_ar', 'primary_btn_label_en', 'primary_btn_url',
        'secondary_btn_label_ar', 'secondary_btn_label_en', 'secondary_btn_url',
        'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::disk(config('filesystems.default', 'public'))->url($this->image_path)
            : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
