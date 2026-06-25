<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    protected $fillable = [
        'name', 'type', 'title_ar', 'title_en', 'subtitle_ar', 'subtitle_en',
        'layout', 'visibility', 'settings', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::flushHomepageCache());
        static::deleted(fn () => static::flushHomepageCache());
    }

    public static function flushHomepageCache(): void
    {
        foreach (['ar', 'en'] as $locale) {
            \Illuminate\Support\Facades\Cache::forget("homepage.data.{$locale}");
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function types(): array
    {
        return [
            'hero'                => 'Hero Banner',
            'brands'              => 'الماركات',
            'featured_brands'     => 'الماركات المميزة',
            'latest_wallpapers'   => 'آخر الخلفيات المضافة',
            'featured_wallpapers' => 'خلفيات مميزة',
            'featured_apps'       => 'تطبيقات مميزة',
            'news'                => 'أخبار',
            'tutorials'           => 'شروحات',
            'statistics'          => 'إحصائيات',
            'cta'                 => 'CTA Block',
            'custom_html'         => 'HTML مخصص',
            'custom_content'      => 'محتوى مخصص',
        ];
    }

    public static function layouts(): array
    {
        return [
            'grid'       => 'Grid',
            'slider'     => 'Slider',
            'carousel'   => 'Carousel',
            'cards'      => 'Cards',
            'list'       => 'List',
            'masonry'    => 'Masonry',
            'hero_cards' => 'Hero Cards',
        ];
    }
}
