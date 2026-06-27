<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        try {
            foreach (DB::table('apps')->whereNotNull('brand_id')->get(['id', 'brand_id']) as $a) {
                try {
                    DB::table('app_brand')->insertOrIgnore(['app_id' => $a->id, 'brand_id' => $a->brand_id]);
                } catch (\Throwable $e) {
                    // skip
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    public function down(): void
    {
        //
    }
};
