<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('position', [
                'header',
                'footer',
                'wallpaper_page_top',
                'wallpaper_page_bottom',
                'wallpaper_page_sidebar',
                'category_page',
                'homepage_banner',
                'search_page'
            ]);
            $table->enum('type', ['html', 'image'])->default('html');
            $table->text('html_code')->nullable();
            $table->string('image_file')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_target')->default('_blank');
            $table->boolean('is_active')->default(true);
            $table->enum('language', ['ar', 'en', 'both'])->default('both');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->bigInteger('impressions_count')->default(0);
            $table->bigInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->index('position');
            $table->index('is_active');
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
