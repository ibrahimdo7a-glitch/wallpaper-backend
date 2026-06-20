<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            // Navigation
            ['key' => 'nav.home', 'value_ar' => 'الرئيسية', 'value_en' => 'Home', 'group' => 'navigation'],
            ['key' => 'nav.categories', 'value_ar' => 'الأقسام', 'value_en' => 'Categories', 'group' => 'navigation'],
            ['key' => 'nav.latest', 'value_ar' => 'الأحدث', 'value_en' => 'Latest', 'group' => 'navigation'],
            ['key' => 'nav.popular', 'value_ar' => 'الأكثر شعبية', 'value_en' => 'Popular', 'group' => 'navigation'],
            ['key' => 'nav.search', 'value_ar' => 'بحث', 'value_en' => 'Search', 'group' => 'navigation'],
            ['key' => 'nav.mobile', 'value_ar' => 'خلفيات الجوال', 'value_en' => 'Mobile Wallpapers', 'group' => 'navigation'],
            ['key' => 'nav.desktop', 'value_ar' => 'خلفيات الكمبيوتر', 'value_en' => 'Desktop Wallpapers', 'group' => 'navigation'],

            // Actions
            ['key' => 'action.download', 'value_ar' => 'تحميل', 'value_en' => 'Download', 'group' => 'actions'],
            ['key' => 'action.like', 'value_ar' => 'إعجاب', 'value_en' => 'Like', 'group' => 'actions'],
            ['key' => 'action.share', 'value_ar' => 'مشاركة', 'value_en' => 'Share', 'group' => 'actions'],
            ['key' => 'action.report', 'value_ar' => 'إبلاغ', 'value_en' => 'Report', 'group' => 'actions'],
            ['key' => 'action.search_placeholder', 'value_ar' => 'ابحث عن خلفيات...', 'value_en' => 'Search wallpapers...', 'group' => 'actions'],

            // Wallpaper
            ['key' => 'wallpaper.views', 'value_ar' => 'مشاهدة', 'value_en' => 'Views', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.downloads', 'value_ar' => 'تحميل', 'value_en' => 'Downloads', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.likes', 'value_ar' => 'إعجاب', 'value_en' => 'Likes', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.resolution', 'value_ar' => 'الدقة', 'value_en' => 'Resolution', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.size', 'value_ar' => 'الحجم', 'value_en' => 'File Size', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.uploaded_by', 'value_ar' => 'رفع بواسطة', 'value_en' => 'Uploaded by', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.category', 'value_ar' => 'القسم', 'value_en' => 'Category', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.tags', 'value_ar' => 'الوسوم', 'value_en' => 'Tags', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.free', 'value_ar' => 'مجاني', 'value_en' => 'Free', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.paid', 'value_ar' => 'مدفوع', 'value_en' => 'Paid', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.price', 'value_ar' => 'السعر', 'value_en' => 'Price', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.buy_now', 'value_ar' => 'اشتر الآن', 'value_en' => 'Buy Now', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.similar', 'value_ar' => 'خلفيات مشابهة', 'value_en' => 'Similar Wallpapers', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.from_category', 'value_ar' => 'من نفس القسم', 'value_en' => 'From Same Category', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.from_uploader', 'value_ar' => 'من نفس المشرف', 'value_en' => 'From Same Uploader', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.device_mobile', 'value_ar' => 'جوال', 'value_en' => 'Mobile', 'group' => 'wallpaper'],
            ['key' => 'wallpaper.device_desktop', 'value_ar' => 'كمبيوتر', 'value_en' => 'Desktop', 'group' => 'wallpaper'],

            // Sort
            ['key' => 'sort.newest', 'value_ar' => 'الأحدث', 'value_en' => 'Newest', 'group' => 'sort'],
            ['key' => 'sort.most_downloaded', 'value_ar' => 'الأكثر تحميلًا', 'value_en' => 'Most Downloaded', 'group' => 'sort'],
            ['key' => 'sort.most_liked', 'value_ar' => 'الأكثر إعجابًا', 'value_en' => 'Most Liked', 'group' => 'sort'],
            ['key' => 'sort.most_viewed', 'value_ar' => 'الأكثر مشاهدة', 'value_en' => 'Most Viewed', 'group' => 'sort'],

            // Pages
            ['key' => 'page.privacy', 'value_ar' => 'سياسة الخصوصية', 'value_en' => 'Privacy Policy', 'group' => 'pages'],
            ['key' => 'page.terms', 'value_ar' => 'الشروط والأحكام', 'value_en' => 'Terms & Conditions', 'group' => 'pages'],

            // Uploader profile
            ['key' => 'uploader.wallpapers', 'value_ar' => 'الخلفيات', 'value_en' => 'Wallpapers', 'group' => 'uploader'],
            ['key' => 'uploader.total_downloads', 'value_ar' => 'إجمالي التحميلات', 'value_en' => 'Total Downloads', 'group' => 'uploader'],
            ['key' => 'uploader.total_likes', 'value_ar' => 'إجمالي الإعجابات', 'value_en' => 'Total Likes', 'group' => 'uploader'],
            ['key' => 'uploader.member_since', 'value_ar' => 'عضو منذ', 'value_en' => 'Member Since', 'group' => 'uploader'],

            // Errors & messages
            ['key' => 'msg.loading', 'value_ar' => 'جاري التحميل...', 'value_en' => 'Loading...', 'group' => 'messages'],
            ['key' => 'msg.no_results', 'value_ar' => 'لا توجد نتائج', 'value_en' => 'No results found', 'group' => 'messages'],
            ['key' => 'msg.liked', 'value_ar' => 'تم الإعجاب!', 'value_en' => 'Liked!', 'group' => 'messages'],
            ['key' => 'msg.already_liked', 'value_ar' => 'أعجبك هذا مسبقًا', 'value_en' => 'Already liked', 'group' => 'messages'],
            ['key' => 'msg.download_started', 'value_ar' => 'بدأ التحميل...', 'value_en' => 'Download started...', 'group' => 'messages'],
            ['key' => 'msg.report_sent', 'value_ar' => 'تم إرسال البلاغ', 'value_en' => 'Report submitted', 'group' => 'messages'],
            ['key' => 'msg.copied', 'value_ar' => 'تم النسخ!', 'value_en' => 'Copied!', 'group' => 'messages'],
        ];

        foreach ($translations as $translation) {
            Translation::firstOrCreate(
                ['key' => $translation['key']],
                $translation
            );
        }

        $this->command->info('Translations seeded successfully.');
    }
}
