<?php

use App\Models\HomepageSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allows new homepage section types (drops the enum CHECK constraint) and
 * seeds a "latest wallpapers" section showing the most recently added wallpapers.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Postgres: enum() created a CHECK constraint — drop it so new types are allowed.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE homepage_sections DROP CONSTRAINT IF EXISTS homepage_sections_type_check');
        }

        // Seed the section once (idempotent) just before the features strip.
        if (!HomepageSection::where('type', 'latest_wallpapers')->exists()) {
            HomepageSection::create([
                'name'       => 'آخر الخلفيات',
                'type'       => 'latest_wallpapers',
                'title_ar'   => 'آخر الخلفيات المضافة',
                'title_en'   => 'Latest Wallpapers',
                'layout'     => 'carousel',
                'visibility' => 'all',
                'sort_order' => 4,   // after statistics, before "featured"
                'is_active'  => true,
                'settings'   => ['limit' => 12],
            ]);
        }
    }

    public function down(): void
    {
        HomepageSection::where('type', 'latest_wallpapers')->delete();
    }
};
