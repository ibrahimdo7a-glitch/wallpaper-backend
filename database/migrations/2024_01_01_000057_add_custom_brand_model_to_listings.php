<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('market_listings')) {
            return;
        }
        Schema::table('market_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('market_listings', 'custom_brand')) {
                $table->string('custom_brand')->nullable();
            }
            if (! Schema::hasColumn('market_listings', 'custom_model')) {
                $table->string('custom_model')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('market_listings', function (Blueprint $table) {
            foreach (['custom_brand', 'custom_model'] as $c) {
                if (Schema::hasColumn('market_listings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
