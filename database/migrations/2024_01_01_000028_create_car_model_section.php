<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which sections each model chooses to show on its page.
 * No rows for a model = show all the brand's model-specific sections (default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_model_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->constrained('car_models')->cascadeOnDelete();
            $table->foreignId('brand_section_id')->constrained('brand_sections')->cascadeOnDelete();
            $table->unique(['car_model_id', 'brand_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_model_section');
    }
};
