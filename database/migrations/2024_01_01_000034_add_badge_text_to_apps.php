<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom, per-app badge text shown on the app card (and Telegram post).
     * Replaces the fixed "works on car screen" label with admin-written text.
     */
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('badge_text_ar', 60)->nullable()->after('short_description_en');
            $table->string('badge_text_en', 60)->nullable()->after('badge_text_ar');
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn(['badge_text_ar', 'badge_text_en']);
        });
    }
};
