<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The live `apps.language` column is NOT NULL without a usable default,
     * so creating an app (the form doesn't set language) failed with a
     * not-null violation. Make it nullable with a sensible default.
     */
    public function up(): void
    {
        // Backfill any existing nulls first, then relax the column.
        DB::statement("UPDATE apps SET language = 'ar' WHERE language IS NULL");

        Schema::table('apps', function (Blueprint $table) {
            $table->string('language')->default('ar')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('language')->default('ar')->nullable(false)->change();
        });
    }
};
