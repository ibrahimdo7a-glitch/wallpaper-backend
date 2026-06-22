<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Upgrade brands table ─────────────────────────────────────────
        Schema::table('brands', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable()->after('is_featured');
            $table->string('accent_color', 7)->nullable()->after('primary_color');
            $table->boolean('maintenance_mode')->default(false)->after('is_active');
            $table->string('telegram_url')->nullable()->after('website_url');
            $table->string('whatsapp_url')->nullable()->after('telegram_url');
            $table->string('channel_url')->nullable()->after('whatsapp_url');
            $table->string('download_cta_url')->nullable()->after('channel_url');
            $table->string('download_cta_label_ar')->nullable()->after('download_cta_url');
            $table->string('download_cta_label_en')->nullable()->after('download_cta_label_ar');
            $table->unsignedInteger('news_count')->default(0)->after('apps_count');
            $table->unsignedInteger('tutorials_count')->default(0)->after('news_count');
            $table->unsignedBigInteger('total_downloads')->default(0)->after('tutorials_count');
            $table->unsignedBigInteger('total_views')->default(0)->after('total_downloads');
        });

        // ─── 2. section_types — master catalog of all possible sections ──────
        Schema::create('section_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // 'wallpapers', 'apps', 'news' …
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('default_icon')->nullable();     // emoji or heroicon
            $table->string('default_layout')->default('grid'); // grid|list|cards|gallery|video_grid|download_list|faq_accordion
            $table->boolean('is_model_specific')->default(false); // can be used per model?
            $table->boolean('is_global')->default(false);   // shows on all brands by default
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── 3. brand_sections — which sections are enabled per brand ────────
        Schema::create('brand_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_type_id')->constrained()->cascadeOnDelete();
            $table->string('slug');                     // brand-specific slug for URL
            $table->string('custom_name_ar')->nullable();
            $table->string('custom_name_en')->nullable();
            $table->text('custom_description_ar')->nullable();
            $table->text('custom_description_en')->nullable();
            $table->string('icon')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('show_in_brand_home')->default(true);
            $table->boolean('show_in_navigation')->default(true);
            $table->boolean('show_in_homepage')->default(false);
            $table->boolean('is_model_specific')->default(false);
            $table->string('layout_type')->default('grid');
            $table->integer('sort_order')->default(0);
            $table->json('settings')->nullable();       // layout-specific config
            $table->timestamps();

            $table->unique(['brand_id', 'section_type_id']);
            $table->unique(['brand_id', 'slug']);
            $table->index(['brand_id', 'is_enabled']);
        });

        // ─── 4. content_items — unified content table ─────────────────────────
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('content_type');             // mirrors section_type.key
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('slug')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('image_path')->nullable();   // main image / cover
            $table->string('thumbnail_path')->nullable();
            $table->string('file_path')->nullable();    // downloadable file
            $table->string('video_url')->nullable();    // YouTube/direct video
            $table->string('external_url')->nullable(); // link for important_links
            $table->json('metadata')->nullable();       // type-specific extras
            $table->string('status')->default('published'); // published|draft|archived
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['brand_id', 'content_type', 'status']);
            $table->index(['brand_section_id', 'status', 'sort_order']);
            $table->index(['car_model_id', 'content_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('brand_sections');
        Schema::dropIfExists('section_types');
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color', 'accent_color', 'maintenance_mode',
                'telegram_url', 'whatsapp_url', 'channel_url',
                'download_cta_url', 'download_cta_label_ar', 'download_cta_label_en',
                'news_count', 'tutorials_count', 'total_downloads', 'total_views',
            ]);
        });
    }
};
