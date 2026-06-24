<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * title_ar is optional for some content types (e.g. bulk-uploaded wallpapers),
 * but the column was NOT NULL — editing an item with an empty title failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->string('title_ar')->nullable(false)->change();
        });
    }
};
