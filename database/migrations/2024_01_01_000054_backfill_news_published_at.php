<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('news_articles')) {
            return;
        }

        // Published articles whose publish time was never stamped (drafts published from the
        // Edit form before the saving-hook fix) — fall back to their creation time so they
        // order newest-first on the homepage and in the admin.
        DB::table('news_articles')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // no-op
    }
};
