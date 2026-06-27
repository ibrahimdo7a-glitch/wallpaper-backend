<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Run outside a transaction so the data seed below can never poison/abort
    // the migration (Postgres aborts the whole tx on any error, even a caught one).
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('app_brand')) {
            Schema::create('app_brand', function (Blueprint $table) {
                $table->id();
                $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                $table->unique(['app_id', 'brand_id']);
            });
        }

        // Carry existing single-brand assignments into the pivot (best-effort).
        try {
            foreach (DB::table('android_apps')->whereNotNull('brand_id')->get(['id', 'brand_id']) as $a) {
                try {
                    DB::table('app_brand')->insertOrIgnore(['app_id' => $a->id, 'brand_id' => $a->brand_id]);
                } catch (\Throwable $e) {
                    // skip orphaned rows
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_brand');
    }
};
