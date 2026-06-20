<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'site_name_ar', 'value' => 'منصة الخلفيات', 'type' => 'string', 'group' => 'general', 'label_ar' => 'اسم الموقع (عربي)', 'label_en' => 'Site Name (Arabic)'],
            ['key' => 'site_name_en', 'value' => 'Wallpaper Platform', 'type' => 'string', 'group' => 'general', 'label_ar' => 'اسم الموقع (إنجليزي)', 'label_en' => 'Site Name (English)'],
            ['key' => 'logo', 'value' => null, 'type' => 'string', 'group' => 'general', 'label_ar' => 'الشعار', 'label_en' => 'Logo'],
            ['key' => 'favicon', 'value' => null, 'type' => 'string', 'group' => 'general', 'label_ar' => 'Favicon', 'label_en' => 'Favicon'],
            ['key' => 'default_language', 'value' => 'ar', 'type' => 'string', 'group' => 'general', 'label_ar' => 'اللغة الافتراضية', 'label_en' => 'Default Language'],

            // Uploads
            ['key' => 'max_upload_size_mb', 'value' => '20', 'type' => 'integer', 'group' => 'uploads', 'label_ar' => 'الحد الأقصى لحجم الصورة (MB)', 'label_en' => 'Max Upload Size (MB)'],
            ['key' => 'allowed_mime_types', 'value' => 'image/jpeg,image/png,image/webp', 'type' => 'string', 'group' => 'uploads', 'label_ar' => 'الصيغ المسموحة', 'label_en' => 'Allowed MIME Types'],
            ['key' => 'require_review', 'value' => '1', 'type' => 'boolean', 'group' => 'uploads', 'label_ar' => 'هل الصور تحتاج مراجعة', 'label_en' => 'Require Review'],

            // Features
            ['key' => 'likes_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label_ar' => 'تفعيل الإعجابات', 'label_en' => 'Enable Likes'],
            ['key' => 'downloads_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label_ar' => 'تفعيل التحميل', 'label_en' => 'Enable Downloads'],
            ['key' => 'reports_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'features', 'label_ar' => 'تفعيل البلاغات', 'label_en' => 'Enable Reports'],

            // Watermarks
            ['key' => 'watermark_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'watermarks', 'label_ar' => 'تفعيل التوقيع', 'label_en' => 'Enable Watermarks'],
            ['key' => 'watermark_force_all', 'value' => '0', 'type' => 'boolean', 'group' => 'watermarks', 'label_ar' => 'إجبار التوقيع على كل الصور', 'label_en' => 'Force Watermark on All'],
            ['key' => 'watermark_default_id', 'value' => null, 'type' => 'integer', 'group' => 'watermarks', 'label_ar' => 'التوقيع الافتراضي', 'label_en' => 'Default Watermark'],
            ['key' => 'download_watermarked', 'value' => '1', 'type' => 'boolean', 'group' => 'watermarks', 'label_ar' => 'تحميل النسخة الموقعة', 'label_en' => 'Download Watermarked Version'],

            // SEO
            ['key' => 'meta_title_ar', 'value' => 'منصة الخلفيات - أفضل خلفيات HD', 'type' => 'string', 'group' => 'seo', 'label_ar' => 'Meta Title (عربي)', 'label_en' => 'Meta Title (Arabic)'],
            ['key' => 'meta_title_en', 'value' => 'Wallpaper Platform - Best HD Wallpapers', 'type' => 'string', 'group' => 'seo', 'label_ar' => 'Meta Title (إنجليزي)', 'label_en' => 'Meta Title (English)'],
            ['key' => 'meta_description_ar', 'value' => 'أفضل منصة لخلفيات الجوال والكمبيوتر بجودة عالية', 'type' => 'string', 'group' => 'seo', 'label_ar' => 'Meta Description (عربي)', 'label_en' => 'Meta Description (Arabic)'],
            ['key' => 'meta_description_en', 'value' => 'The best platform for high quality mobile and desktop wallpapers', 'type' => 'string', 'group' => 'seo', 'label_ar' => 'Meta Description (إنجليزي)', 'label_en' => 'Meta Description (English)'],

            // Security
            ['key' => 'login_attempts_limit', 'value' => '5', 'type' => 'integer', 'group' => 'security', 'label_ar' => 'عدد محاولات الدخول قبل القفل', 'label_en' => 'Login Attempts Before Lock'],
            ['key' => 'login_lockout_minutes', 'value' => '30', 'type' => 'integer', 'group' => 'security', 'label_ar' => 'مدة القفل (دقيقة)', 'label_en' => 'Lockout Duration (minutes)'],

            // Future: Commerce
            ['key' => 'commerce_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'commerce', 'label_ar' => 'تفعيل نظام البيع', 'label_en' => 'Enable Commerce'],
            ['key' => 'default_currency', 'value' => 'QAR', 'type' => 'string', 'group' => 'commerce', 'label_ar' => 'العملة الافتراضية', 'label_en' => 'Default Currency'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Settings seeded successfully.');
    }
}
