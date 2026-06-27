<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_brand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('android_apps')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->unique(['app_id', 'brand_id']);
        });

        // Carry existing single-brand assignments into the pivot.
        try {
            foreach (DB::table('android_apps')->whereNotNull('brand_id')->get(['id', 'brand_id']) as $a) {
                DB::table('app_brand')->insertOrIgnore(['app_id' => $a->id, 'brand_id' => $a->brand_id]);
            }
        } catch (\Throwable $e) {
            // non-fatal — pivot just starts empty
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_brand');
    }
};
