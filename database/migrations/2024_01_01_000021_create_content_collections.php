<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Collections — flexible admin-defined groups (folders) inside a brand.
 * e.g. Brand "Leopard" → Section "Wallpapers" → Collections: Leopard 5, Leopard 8, Qatar, UAE...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            // optional scope to a specific section (e.g. only the wallpapers section)
            $table->foreignId('brand_section_id')->nullable()->constrained('brand_sections')->nullOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('image_path')->nullable();   // cover/folder image
            $table->string('icon')->nullable();          // emoji or flag
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['brand_id', 'slug']);
            $table->index(['brand_id', 'brand_section_id', 'is_active']);
        });

        // Link content items to a collection (nullable — content can exist without a group)
        Schema::table('content_items', function (Blueprint $table) {
            $table->foreignId('content_collection_id')->nullable()->after('brand_section_id')
                ->constrained('content_collections')->nullOnDelete();
            $table->index(['content_collection_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_collection_id');
        });
        Schema::dropIfExists('content_collections');
    }
};
