<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move the "features" strip (custom_content) to the very bottom of the
     * homepage, below the latest wallpapers section (which sits at sort_order 6).
     * Only touches sort_order — leaves the section's settings/items untouched.
     */
    public function up(): void
    {
        DB::table('homepage_sections')->where('type', 'custom_content')->update(['sort_order' => 7]);

        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
        }
    }

    public function down(): void
    {
        DB::table('homepage_sections')->where('type', 'custom_content')->update(['sort_order' => 6]);

        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
        }
    }
};
