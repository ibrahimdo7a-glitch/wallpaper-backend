<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a content collection (sub-section) belong to a specific car model,
 * enabling per-model sub-sections inside a model's section.
 * car_model_id NULL = brand-level collection (existing behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_collections', function (Blueprint $table) {
            $table->foreignId('car_model_id')->nullable()->after('brand_section_id')
                ->constrained('car_models')->nullOnDelete();
            $table->index(['brand_id', 'car_model_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('content_collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('car_model_id');
        });
    }
};
