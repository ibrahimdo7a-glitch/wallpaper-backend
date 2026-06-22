<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Link wallpapers to brands/models
        Schema::table('wallpapers', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->after('brand_id')->constrained('car_models')->nullOnDelete();
            $table->index('brand_id');
            $table->index('car_model_id');
        });

        // Upgrade apps table with full fields
        Schema::table('apps', function (Blueprint $table) {
            // Brand/model links
            $table->foreignId('brand_id')->nullable()->after('app_category_id')->constrained('brands')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->after('brand_id')->constrained('car_models')->nullOnDelete();

            // Short description
            $table->string('short_description_ar', 300)->nullable()->after('description_en');
            $table->string('short_description_en', 300)->nullable()->after('short_description_ar');

            // Cover image
            $table->string('cover_image')->nullable()->after('icon_file');

            // URLs
            $table->string('play_store_url')->nullable()->after('external_url');
            $table->string('official_website_url')->nullable()->after('play_store_url');

            // Technical info
            $table->string('developer_name')->nullable()->after('developer');
            $table->string('language')->default('ar')->after('developer_name');
            $table->string('apk_sha256')->nullable()->after('apk_file');

            // Car compatibility flags
            $table->boolean('requires_internet')->default(false)->after('language');
            $table->boolean('requires_login')->default(false)->after('requires_internet');
            $table->boolean('works_on_car_screen')->default(false)->after('requires_login');
            $table->boolean('tested_on_car')->default(false)->after('works_on_car_screen');

            // Safety & verification
            $table->enum('safety_status', ['verified', 'tested', 'external_source', 'not_tested'])->default('not_tested')->after('tested_on_car');

            // Importance flags
            $table->boolean('is_important')->default(false)->after('is_featured');
            $table->boolean('is_recommended')->default(false)->after('is_important');
            $table->boolean('is_verified')->default(false)->after('is_recommended');
            $table->boolean('show_on_home')->default(false)->after('is_verified');

            // Counters
            $table->unsignedBigInteger('views_count')->default(0)->after('downloads_count');
            $table->decimal('rating_average', 3, 2)->default(0)->after('views_count');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_average');

            $table->index('brand_id');
            $table->index('car_model_id');
            $table->index('is_important');
            $table->index('safety_status');
            $table->index('show_on_home');
        });

        // Upgrade app_installation_steps with description fields
        Schema::table('app_installation_steps', function (Blueprint $table) {
            $table->text('description_ar')->nullable()->after('title_en');
            $table->text('description_en')->nullable()->after('description_ar');
        });

        // App versions history
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('version');
            $table->string('apk_file')->nullable();
            $table->string('apk_sha256')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('changelog_ar')->nullable();
            $table->text('changelog_en')->nullable();
            $table->date('release_date')->nullable();
            $table->boolean('is_stable')->default(true);
            $table->timestamps();
            $table->index('app_id');
        });

        // App screenshots gallery
        Schema::create('app_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('image_file');
            $table->string('caption_ar')->nullable();
            $table->string('caption_en')->nullable();
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('app_id');
        });

        // App compatibility per brand/model
        Schema::create('app_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->string('android_version')->nullable();
            $table->enum('compatibility_status', ['compatible', 'partial', 'incompatible', 'unknown'])->default('unknown');
            $table->text('notes_ar')->nullable();
            $table->text('notes_en')->nullable();
            $table->timestamps();
            $table->index('app_id');
        });

        // Pivot: important apps scoped per car model
        Schema::create('car_model_important_apps', function (Blueprint $table) {
            $table->foreignId('car_model_id')->constrained('car_models')->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->primary(['car_model_id', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_model_important_apps');
        Schema::dropIfExists('app_compatibilities');
        Schema::dropIfExists('app_screenshots');
        Schema::dropIfExists('app_versions');

        Schema::table('app_installation_steps', function (Blueprint $table) {
            $table->dropColumn(['description_ar', 'description_en']);
        });

        Schema::table('apps', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['car_model_id']);
            $table->dropColumn([
                'brand_id', 'car_model_id', 'short_description_ar', 'short_description_en',
                'cover_image', 'play_store_url', 'official_website_url', 'developer_name',
                'language', 'apk_sha256', 'requires_internet', 'requires_login',
                'works_on_car_screen', 'tested_on_car', 'safety_status',
                'is_important', 'is_recommended', 'is_verified', 'show_on_home',
                'views_count', 'rating_average', 'rating_count',
            ]);
        });

        Schema::table('wallpapers', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['car_model_id']);
            $table->dropColumn(['brand_id', 'car_model_id']);
        });
    }
};
