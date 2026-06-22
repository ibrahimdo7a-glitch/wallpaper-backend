<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('summary_ar')->nullable();
            $table->text('summary_en')->nullable();
            $table->longText('content_ar')->nullable();
            $table->longText('content_en')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('video_url')->nullable();
            $table->string('difficulty')->default('easy');  // easy, medium, hard
            $table->string('duration_label')->nullable();   // e.g. "5 دقائق"
            $table->enum('status', ['published', 'draft', 'hidden'])->default('published');
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->string('meta_title_ar')->nullable();
            $table->string('meta_title_en')->nullable();
            $table->text('meta_description_ar')->nullable();
            $table->text('meta_description_en')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('brand_id');
            $table->index('car_model_id');
            $table->index('status');
            $table->index('is_featured');
        });

        Schema::create('car_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('file_path');
            $table->string('file_type')->nullable();      // pdf, zip, apk, img
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('version')->nullable();
            $table->enum('status', ['published', 'hidden'])->default('published');
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('brand_id');
            $table->index('car_model_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_files');
        Schema::dropIfExists('tutorials');
    }
};
