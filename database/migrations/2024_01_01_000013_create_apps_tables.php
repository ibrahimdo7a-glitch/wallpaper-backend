<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // App categories (independent from wallpaper categories)
        Schema::create('app_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();           // emoji or heroicon name
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('apps_count')->default(0);
            $table->timestamps();
        });

        // Apps
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_category_id')->nullable()->constrained('app_categories')->nullOnDelete();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('icon_file')->nullable();           // app icon stored on R2
            $table->string('apk_file')->nullable();            // APK file on R2
            $table->string('external_url')->nullable();        // Play Store / direct link
            $table->string('version')->nullable();             // e.g. "2.1.4"
            $table->string('package_name')->nullable();        // e.g. "com.example.app"
            $table->string('developer')->nullable();
            $table->string('min_android')->nullable();         // e.g. "Android 8.0+"
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->string('status')->default('published');    // published / pending / hidden
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_free')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title_ar')->nullable();
            $table->string('meta_title_en')->nullable();
            $table->text('meta_description_ar')->nullable();
            $table->text('meta_description_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Installation steps (up to 6 per app)
        Schema::create('app_installation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->tinyInteger('step_number');             // 1–6
            $table->string('image_file');                   // screenshot stored on R2
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->timestamps();

            $table->unique(['app_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_installation_steps');
        Schema::dropIfExists('apps');
        Schema::dropIfExists('app_categories');
    }
};
