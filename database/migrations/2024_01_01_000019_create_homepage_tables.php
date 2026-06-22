<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->text('subtitle_ar')->nullable();
            $table->text('subtitle_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('image_path')->nullable();
            $table->string('bg_color', 20)->default('#000000');
            $table->string('text_color', 20)->default('#ffffff');
            $table->string('primary_btn_label_ar')->nullable();
            $table->string('primary_btn_label_en')->nullable();
            $table->string('primary_btn_url')->nullable();
            $table->string('secondary_btn_label_ar')->nullable();
            $table->string('secondary_btn_label_en')->nullable();
            $table->string('secondary_btn_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', [
                'hero', 'brands', 'featured_brands',
                'featured_wallpapers', 'featured_apps',
                'news', 'tutorials', 'statistics',
                'cta', 'custom_html', 'custom_content',
            ]);
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('subtitle_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->enum('layout', ['grid', 'slider', 'carousel', 'cards', 'list', 'masonry', 'hero_cards'])->default('grid');
            $table->enum('visibility', ['all', 'desktop', 'mobile', 'tablet'])->default('all');
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->string('label_ar');
            $table->string('label_en')->nullable();
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('navigation_items')->onDelete('set null');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('open_in_new_tab')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('hero_banners');
    }
};
