<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Railway runs migrate --force on deploy; keep this resilient.
    public $withinTransaction = false;

    public function up(): void
    {
        // Append-only time-series of pageviews / events (drives trends & sources).
        if (! Schema::hasTable('analytics_events')) {
            Schema::create('analytics_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('visitor_id', 64)->index();
                $table->string('session_id', 64)->nullable();
                $table->unsignedBigInteger('member_id')->nullable()->index();
                $table->string('type', 20)->default('pageview')->index(); // pageview | event
                $table->string('name', 60)->nullable();                    // for custom events
                $table->string('path', 512)->nullable();
                $table->string('referrer_host', 255)->nullable();
                $table->string('source', 40)->nullable()->index();         // google | telegram | direct | ...
                $table->char('country', 2)->nullable()->index();
                $table->string('device', 10)->nullable();                  // mobile | desktop | tablet
                $table->timestamp('created_at')->index();
            });
        }

        // One row per unique visitor — current identity + presence (drives online/live/geo).
        if (! Schema::hasTable('analytics_visitors')) {
            Schema::create('analytics_visitors', function (Blueprint $table) {
                $table->string('visitor_id', 64)->primary();
                $table->unsignedBigInteger('member_id')->nullable()->index();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->char('country', 2)->nullable()->index();
                $table->string('city', 80)->nullable();
                $table->string('device', 10)->nullable();
                $table->string('os', 40)->nullable();
                $table->string('browser', 40)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('last_path', 512)->nullable();
                $table->string('source', 40)->nullable();                  // first-touch source
                $table->unsignedInteger('total_views')->default(0);
                $table->unsignedInteger('sessions')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_visitors');
    }
};
