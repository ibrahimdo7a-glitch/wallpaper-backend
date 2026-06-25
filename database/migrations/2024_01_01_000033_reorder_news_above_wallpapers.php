<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time reorder: lift the News section above the statistics and
     * wallpaper sections on the homepage. hero(1) + brands(2) stay on top,
     * then news(3), statistics(4), featured_wallpapers(5), latest_wallpapers(6).
     */
    public function up(): void
    {
        $order = [
            'news'               => 3,
            'statistics'         => 4,
            'featured_wallpapers'=> 5,
            'latest_wallpapers'  => 6,
        ];

        foreach ($order as $type => $sort) {
            DB::table('homepage_sections')->where('type', $type)->update(['sort_order' => $sort]);
        }

        // Flush cached homepage payloads so the new order shows immediately.
        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
        }
    }

    public function down(): void
    {
        // Restore the previous ordering (news last).
        $order = [
            'statistics'         => 3,
            'featured_wallpapers'=> 4,
            'latest_wallpapers'  => 4,
            'news'               => 5,
        ];

        foreach ($order as $type => $sort) {
            DB::table('homepage_sections')->where('type', $type)->update(['sort_order' => $sort]);
        }

        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
        }
    }
};
