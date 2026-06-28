<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasColumn('app_screenshots', 'image_file')) {
            Schema::table('app_screenshots', function (Blueprint $table) {
                $table->string('image_file')->nullable()->change();
            });
        }
        if (Schema::hasColumn('app_installation_steps', 'image_file')) {
            Schema::table('app_installation_steps', function (Blueprint $table) {
                $table->string('image_file')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // no-op (leaving the columns nullable is safe)
    }
};
