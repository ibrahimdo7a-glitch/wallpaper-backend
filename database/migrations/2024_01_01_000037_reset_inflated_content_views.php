<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The old GET /content/{id} incremented views_count on every server render /
     * ISR revalidation, massively inflating the count (678 views vs 9 real visitors).
     * Views are now counted client-side only, so reset to a clean slate to start
     * counting real human views.
     */
    public function up(): void
    {
        DB::table('content_items')->update(['views_count' => 0]);

        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
        }
    }

    public function down(): void
    {
        // Inflated historical values are intentionally not restorable.
    }
};
