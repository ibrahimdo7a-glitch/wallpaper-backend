<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->string('author_name')->nullable()->after('description_en');   // designer/creator
            $table->unsignedBigInteger('likes_count')->default(0)->after('downloads_count');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropColumn(['author_name', 'likes_count']);
        });
    }
};
