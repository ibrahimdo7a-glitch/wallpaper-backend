<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('apps') && ! Schema::hasColumn('apps', 'sort_order')) {
            Schema::table('apps', function (Blueprint $table) {
                $table->integer('sort_order')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('apps', 'sort_order')) {
            Schema::table('apps', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
