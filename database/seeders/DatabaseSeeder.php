<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            SuperAdminSeeder::class,
            SettingsSeeder::class,
            TranslationSeeder::class,
            SectionTypesSeeder::class,
            BrandsAndModelsSeeder::class,
            NewsCategoriesSeeder::class,
            HomepageSeeder::class,
        ]);
    }
}
