<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categories within a listing type (e.g. parts → batteries/tires; accessories → interior/exterior).
        Schema::create('market_categories', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type');           // part / accessory / car_sale / car_request / service
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();        // emoji
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('listing_type');
        });

        // One unified table for every marketplace listing type.
        Schema::create('market_listings', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type')->index();   // part / accessory / car_sale / car_request / service
            $table->foreignId('market_category_id')->nullable()->constrained('market_categories')->nullOnDelete();

            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('QAR');
            $table->boolean('is_negotiable')->default(false);
            $table->string('condition')->nullable();    // new / used / na

            $table->string('country')->nullable();
            $table->string('city')->nullable();

            // Compatibility / the car being sold — reuse the existing brand & model catalog.
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->unsignedSmallInteger('year')->nullable();      // car
            $table->unsignedInteger('mileage')->nullable();        // car
            $table->json('specs')->nullable();                     // flexible per-type extras

            $table->json('images')->nullable();                    // array of R2 paths

            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('contact_telegram')->nullable();

            $table->boolean('is_paid_listing')->default(false);    // admin marks paid / free
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('published');        // pending / published / sold / hidden
            $table->string('source')->default('admin');            // admin / user
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['listing_type', 'status']);
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listings');
        Schema::dropIfExists('market_categories');
    }
};
