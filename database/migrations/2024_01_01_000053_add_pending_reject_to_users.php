<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'pending_reject_listing_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('pending_reject_listing_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pending_reject_listing_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('pending_reject_listing_id'));
        }
    }
};
