<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectionType extends Model
{
    protected $fillable = [
        'key', 'name_ar', 'name_en', 'description_ar', 'description_en',
        'default_icon', 'default_layout', 'is_model_specific',
        'is_global', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_model_specific' => 'boolean',
        'is_global'         => 'boolean',
        'is_active'         => 'boolean',
    ];

    public function brandSections(): HasMany
    {
        return $this->hasMany(BrandSection::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Layouts available for selection in UI */
    public static function layouts(): array
    {
        return [
            'grid'           => 'شبكة (Grid)',
            'list'           => 'قائمة (List)',
            'cards'          => 'بطاقات (Cards)',
            'gallery'        => 'معرض صور (Gallery)',
            'video_grid'     => 'شبكة فيديو (Video Grid)',
            'download_list'  => 'قائمة تحميل (Download List)',
            'faq_accordion'  => 'سؤال وجواب (FAQ Accordion)',
        ];
    }
}
