<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Seeder;

class NewsCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name_ar' => 'أخبار عامة',     'name_en' => 'General News',    'slug' => 'general',       'color' => '#6b7280', 'icon' => '📰', 'sort_order' => 1],
            ['name_ar' => 'إطلاقات جديدة',  'name_en' => 'New Launches',    'slug' => 'launches',      'color' => '#10b981', 'icon' => '🚀', 'sort_order' => 2],
            ['name_ar' => 'مراجعات',         'name_en' => 'Reviews',         'slug' => 'reviews',       'color' => '#3b82f6', 'icon' => '⭐', 'sort_order' => 3],
            ['name_ar' => 'تقنية وذكاء',    'name_en' => 'Tech & AI',       'slug' => 'tech',          'color' => '#8b5cf6', 'icon' => '🤖', 'sort_order' => 4],
            ['name_ar' => 'أسعار وعروض',    'name_en' => 'Prices & Deals',  'slug' => 'prices',        'color' => '#f59e0b', 'icon' => '💰', 'sort_order' => 5],
            ['name_ar' => 'شحن كهربائي',    'name_en' => 'EV Charging',     'slug' => 'charging',      'color' => '#06b6d4', 'icon' => '⚡', 'sort_order' => 6],
            ['name_ar' => 'مقارنات',         'name_en' => 'Comparisons',     'slug' => 'comparisons',   'color' => '#ef4444', 'icon' => '⚖️', 'sort_order' => 7],
            ['name_ar' => 'تجارب قيادة',    'name_en' => 'Test Drives',     'slug' => 'test-drives',   'color' => '#f97316', 'icon' => '🏎️', 'sort_order' => 8],
        ];

        foreach ($categories as $cat) {
            NewsCategory::updateOrCreate(['slug' => $cat['slug']], array_merge($cat, ['is_active' => true]));
        }

        $this->command->info('✅ تم إضافة تصنيفات الأخبار');
    }
}
