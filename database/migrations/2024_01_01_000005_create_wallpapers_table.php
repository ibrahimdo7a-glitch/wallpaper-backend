<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallpapers', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            // File storage paths (relative to R2 bucket)
            $table->string('original_file');
            $table->string('webp_file')->nullable();
            $table->string('thumbnail_file')->nullable();
            $table->string('watermarked_file')->nullable();
            $table->string('watermarked_webp_file')->nullable();
            $table->string('mobile_file')->nullable();
            $table->string('desktop_file')->nullable();
            $table->string('preview_file')->nullable(); // for paid wallpapers

            // File metadata
            $table->bigInteger('file_size')->default(0);
            $table->string('mime_type');
            $table->integer('width')->default(0);
            $table->integer('height')->default(0);
            $table->string('resolution_label')->nullable(); // 4K, 8K, HD, FHD, QHD
            $table->string('image_hash')->nullable();      // duplicate detection
            $table->enum('device_type', ['mobile', 'desktop', 'tablet', 'all'])->default('all');

            // Status & workflow
            $table->enum('status', ['pending', 'published', 'rejected', 'hidden'])->default('pending');
            $table->text('rejection_reason')->nullable();

            // Relationships
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('watermark_id')->nullable()->constrained('watermarks')->nullOnDelete();

            // Watermark
            $table->boolean('watermark_applied')->default(false);

            // Monetization (future-ready)
            $table->boolean('is_free')->default(true);
            $table->boolean('is_paid')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 10)->default('QAR');
            $table->enum('license_type', ['personal', 'commercial', 'extended'])->default('personal');
            $table->string('premium_file')->nullable();
            $table->integer('purchased_download_limit')->nullable();

            // Counters (denormalized for performance)
            $table->bigInteger('views_count')->default(0);
            $table->bigInteger('downloads_count')->default(0);
            $table->bigInteger('likes_count')->default(0);
            $table->bigInteger('sales_count')->default(0);

            // Flags
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_safe')->default(true);

            // SEO
            $table->string('meta_title_ar')->nullable();
            $table->string('meta_title_en')->nullable();
            $table->text('meta_description_ar')->nullable();
            $table->text('meta_description_en')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('slug');
            $table->index('status');
            $table->index('uploaded_by');
            $table->index('category_id');
            $table->index('is_featured');
            $table->index('device_type');
            $table->index('is_free');
            $table->index('is_paid');
            $table->index('published_at');
            $table->index('downloads_count');
            $table->index('likes_count');
            $table->index('views_count');
            $table->index('image_hash');
            $table->index(['status', 'published_at']);
            $table->index(['status', 'downloads_count']);
            $table->index(['status', 'likes_count']);
        });

        Schema::create('wallpaper_tag', function (Blueprint $table) {
            $table->foreignId('wallpaper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['wallpaper_id', 'tag_id']);
        });

        Schema::create('wallpaper_category', function (Blueprint $table) {
            $table->foreignId('wallpaper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['wallpaper_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallpaper_category');
        Schema::dropIfExists('wallpaper_tag');
        Schema::dropIfExists('wallpapers');
    }
};
