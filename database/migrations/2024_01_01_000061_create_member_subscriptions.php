<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // A member opts in to market alerts per channel (cars / parts), optionally
        // scoped to one brand (brand_id null = all brands in that channel).
        if (! Schema::hasTable('member_subscriptions')) {
            Schema::create('member_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->string('channel', 20);                                            // cars | parts
                $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete(); // null = كل الماركات
                $table->timestamps();

                $table->unique(['member_id', 'channel', 'brand_id']);
                $table->index(['channel', 'brand_id']);
            });
        }

        // So each published listing DMs matching subscribers only once.
        if (Schema::hasTable('market_listings') && ! Schema::hasColumn('market_listings', 'subscribers_notified')) {
            Schema::table('market_listings', function (Blueprint $table) {
                $table->boolean('subscribers_notified')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscriptions');
        if (Schema::hasTable('market_listings') && Schema::hasColumn('market_listings', 'subscribers_notified')) {
            Schema::table('market_listings', function (Blueprint $table) {
                $table->dropColumn('subscribers_notified');
            });
        }
    }
};
