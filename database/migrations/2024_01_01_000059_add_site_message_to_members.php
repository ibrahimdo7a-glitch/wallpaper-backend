<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('members') && ! Schema::hasColumn('members', 'site_message')) {
            Schema::table('members', function (Blueprint $table) {
                // A one-time in-site message from admin; shown to the member once then cleared.
                $table->text('site_message')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('members') && Schema::hasColumn('members', 'site_message')) {
            Schema::table('members', fn (Blueprint $table) => $table->dropColumn('site_message'));
        }
    }
};
