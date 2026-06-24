<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets content items (model/brand wallpapers) carry a burned-in watermark while
 * preserving the clean original, so a watermark can be changed or removed later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->foreignId('watermark_id')->nullable()->after('designer_id')
                ->constrained('watermarks')->nullOnDelete();
            $table->string('original_image_path')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('watermark_id');
            $table->dropColumn('original_image_path');
        });
    }
};
