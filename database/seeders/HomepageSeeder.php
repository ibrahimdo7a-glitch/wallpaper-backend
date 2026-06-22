<?php

namespace Database\Seeders;

use App\Models\HeroBanner;
use App\Models\HomepageSection;
use App\Models\NavigationItem;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Navigation ───────────────────────────────────────────────────────
        NavigationItem::truncate();
        $nav = [
            ['label_ar' => 'الرئيسية',  'label_en' => 'Home',       'url' => '/',            'icon' => '🏠', 'sort_order' => 1],
            ['label_ar' => 'الماركات',   'label_en' => 'Brands',     'url' => '/brands',      'icon' => '🚗', 'sort_order' => 2],
            ['label_ar' => 'التطبيقات', 'label_en' => 'Apps',       'url' => '/apps',        'icon' => '📱', 'sort_order' => 3],
            ['label_ar' => 'الأخبار',    'label_en' => 'News',       'url' => '/news',        'icon' => '📰', 'sort_order' => 4],
            ['label_ar' => 'الشروحات',   'label_en' => 'Tutorials',  'url' => '/tutorials',   'icon' => '🎓', 'sort_order' => 5],
            ['label_ar' => 'الملفات',    'label_en' => 'Files',      'url' => '/files',       'icon' => '📁', 'sort_order' => 6],
            ['label_ar' => 'عن الموقع',  'label_en' => 'About',      'url' => '/about',       'icon' => 'ℹ️', 'sort_order' => 7],
        ];
        foreach ($nav as $item) {
            NavigationItem::create(array_merge($item, ['is_active' => true]));
        }

        // ─── Hero Banner ──────────────────────────────────────────────────────
        HeroBanner::truncate();
        HeroBanner::create([
            'title_ar'             => 'اختر ماركة سيارتك',
            'title_en'             => 'Choose Your Car Brand',
            'subtitle_ar'          => 'كل ما تحتاجه لسيارتك في مكان واحد',
            'subtitle_en'          => 'Everything you need for your car in one place',
            'description_ar'       => 'خلفيات عالية الجودة • تطبيقات مفيدة • شروحات مبسطة • أخبار حصرية • ملفات وتحميلات',
            'description_en'       => 'High quality wallpapers • Useful apps • Simple tutorials • Exclusive news • Files & downloads',
            'bg_color'             => '#ffffff',
            'text_color'           => '#111827',
            'primary_btn_label_ar' => 'عرض الماركات',
            'primary_btn_label_en' => 'Browse Brands',
            'primary_btn_url'      => '/brands',
            'secondary_btn_label_ar' => 'آخر الأخبار',
            'secondary_btn_label_en' => 'Latest News',
            'secondary_btn_url'    => '/news',
            'is_active'            => true,
            'sort_order'           => 0,
        ]);

        // ─── Homepage Sections ────────────────────────────────────────────────
        HomepageSection::truncate();
        $sections = [
            [
                'name'     => 'Hero Section',
                'type'     => 'hero',
                'layout'   => 'grid',
                'sort_order' => 1,
                'settings' => null,
            ],
            [
                'name'     => 'شريط الماركات',
                'type'     => 'brands',
                'title_ar' => 'الماركات',
                'title_en' => 'Brands',
                'layout'   => 'carousel',
                'sort_order' => 2,
                'settings' => ['limit' => 20],
            ],
            [
                'name'     => 'الإحصائيات',
                'type'     => 'statistics',
                'layout'   => 'grid',
                'sort_order' => 3,
                'settings' => [],
            ],
            [
                'name'     => 'الأكثر تميزاً',
                'type'     => 'featured_wallpapers',
                'title_ar' => 'الأكثر تميزاً',
                'title_en' => 'Featured',
                'layout'   => 'hero_cards',
                'sort_order' => 4,
                'settings' => ['limit' => 5],
            ],
            [
                'name'     => 'آخر الأخبار',
                'type'     => 'news',
                'title_ar' => 'آخر الأخبار',
                'title_en' => 'Latest News',
                'layout'   => 'cards',
                'sort_order' => 5,
                'settings' => ['limit' => 6, 'featured_only' => false],
            ],
            [
                'name'     => 'مميزات الموقع',
                'type'     => 'custom_content',
                'layout'   => 'grid',
                'sort_order' => 6,
                'settings' => [
                    'items' => [
                        ['icon' => '📦', 'title_ar' => 'محتوى متنوع',     'title_en' => 'Diverse Content',    'subtitle_ar' => 'خلفيات، تطبيقات، شروحات، ملفات', 'subtitle_en' => 'Wallpapers, apps, tutorials, files'],
                        ['icon' => '⚡', 'title_ar' => 'تحميل سريع',      'title_en' => 'Fast Downloads',     'subtitle_ar' => 'خوادم سريعة لتحميل الملفات',      'subtitle_en' => 'Fast servers for file downloads'],
                        ['icon' => '🔄', 'title_ar' => 'تحديثات مستمرة',  'title_en' => 'Regular Updates',    'subtitle_ar' => 'نقوم بتحديث المحتوى باستمرار',    'subtitle_en' => 'We update content regularly'],
                        ['icon' => '👥', 'title_ar' => 'مجتمع نشط',       'title_en' => 'Active Community',   'subtitle_ar' => 'انضم إلى مجتمع مالكي السيارات',   'subtitle_en' => 'Join the car owners community'],
                        ['icon' => '✅', 'title_ar' => 'محتوى موثوق',     'title_en' => 'Trusted Content',    'subtitle_ar' => 'جميع المحتويات مختبرة وآمنة',     'subtitle_en' => 'All content is tested and safe'],
                    ],
                ],
            ],
        ];

        foreach ($sections as $section) {
            HomepageSection::create(array_merge($section, ['is_active' => true]));
        }
    }
}
