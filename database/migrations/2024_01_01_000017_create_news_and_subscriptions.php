<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // News categories
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->string('color')->default('#6366f1');  // UI color
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('articles_count')->default(0);
            $table->timestamps();
        });

        // News articles
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_category_id')->nullable()->constrained('news_categories')->nullOnDelete();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('summary_ar')->nullable();
            $table->text('summary_en')->nullable();
            $table->longText('content_ar')->nullable();
            $table->longText('content_en')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_name')->nullable();
            $table->string('author_name')->nullable();
            $table->enum('status', ['published', 'draft', 'hidden'])->default('published');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_breaking')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->string('meta_title_ar')->nullable();
            $table->string('meta_title_en')->nullable();
            $table->text('meta_description_ar')->nullable();
            $table->text('meta_description_en')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('news_category_id');
            $table->index('status');
            $table->index('is_featured');
            $table->index('is_breaking');
            $table->index('published_at');
        });

        // Pivot: article ↔ brand
        Schema::create('news_article_brand', function (Blueprint $table) {
            $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->primary(['news_article_id', 'brand_id']);
        });

        // Pivot: article ↔ car_model
        Schema::create('news_article_car_model', function (Blueprint $table) {
            $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
            $table->foreignId('car_model_id')->constrained('car_models')->cascadeOnDelete();
            $table->primary(['news_article_id', 'car_model_id']);
        });

        // Newsletter subscriptions
        Schema::create('news_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('token')->unique();                     // for unsubscribe
            $table->boolean('is_verified')->default(false);
            $table->boolean('subscribe_all')->default(false);      // all news
            $table->enum('status', ['active', 'unsubscribed'])->default('active');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('token');
            $table->index('status');
        });

        // Subscription preferences per brand
        Schema::create('subscription_brands', function (Blueprint $table) {
            $table->foreignId('news_subscription_id')->constrained('news_subscriptions')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->primary(['news_subscription_id', 'brand_id']);
        });

        // Subscription preferences per car model
        Schema::create('subscription_car_models', function (Blueprint $table) {
            $table->foreignId('news_subscription_id')->constrained('news_subscriptions')->cascadeOnDelete();
            $table->foreignId('car_model_id')->constrained('car_models')->cascadeOnDelete();
            $table->primary(['news_subscription_id', 'car_model_id']);
        });

        // Subscription preferences per news category
        Schema::create('subscription_news_categories', function (Blueprint $table) {
            $table->foreignId('news_subscription_id')->constrained('news_subscriptions')->cascadeOnDelete();
            $table->foreignId('news_category_id')->constrained('news_categories')->cascadeOnDelete();
            $table->primary(['news_subscription_id', 'news_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_news_categories');
        Schema::dropIfExists('subscription_car_models');
        Schema::dropIfExists('subscription_brands');
        Schema::dropIfExists('news_subscriptions');
        Schema::dropIfExists('news_article_car_model');
        Schema::dropIfExists('news_article_brand');
        Schema::dropIfExists('news_articles');
        Schema::dropIfExists('news_categories');
    }
};
