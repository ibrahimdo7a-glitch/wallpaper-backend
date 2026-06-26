<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Premium / paid wallpapers — set per wallpaper from its own edit form. */
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('is_pinned');
            $table->decimal('price', 10, 2)->nullable()->after('is_paid');
            $table->string('currency', 3)->default('QAR')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'price', 'currency']);
        });
    }
};
