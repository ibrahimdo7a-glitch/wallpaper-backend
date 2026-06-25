<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seed the site visitor counter row (used by the homepage statistics). */
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'site_visits')->exists()) {
            DB::table('settings')->insert([
                'key'        => 'site_visits',
                'value'      => '0',
                'type'       => 'integer',
                'group'      => 'stats',
                'label_ar'   => 'عدد الزوار',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'site_visits')->delete();
    }
};
