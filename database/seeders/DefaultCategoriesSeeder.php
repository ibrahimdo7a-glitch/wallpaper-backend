<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name_ar' => 'ليوبارد 5',
                'name_en' => 'Leopard 5',
                'slug' => 'leopard-5',
                'description_ar' => 'خلفيات سيارة BYD ليوبارد 5 - القوة والصلابة',
                'description_en' => 'BYD Leopard 5 wallpapers - Power & Toughness',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name_ar' => 'ليوبارد 8',
                'name_en' => 'Leopard 8',
                'slug' => 'leopard-8',
                'description_ar' => 'خلفيات سيارة BYD ليوبارد 8 - الفخامة والتقدم',
                'description_en' => 'BYD Leopard 8 wallpapers - Luxury & Progress',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name_ar' => 'السيارات الصغيرة',
                'name_en' => 'Compact SUVs',
                'slug' => 'compact',
                'description_ar' => 'خلفيات السيارات الصغيرة من BYD - عملية وذكية',
                'description_en' => 'BYD Compact SUV wallpapers - Smart & Practical',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($categories as $data) {
            Category::firstOrCreate(['slug' => $data['slug']], $data);
        }

        $this->command->info('Default categories seeded.');
    }
}
