<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('market_listings') && ! Schema::hasColumn('market_listings', 'rejection_reason')) {
            Schema::table('market_listings', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id')->nullable();
                }
                if (! Schema::hasColumn('users', 'telegram_link_code')) {
                    $table->string('telegram_link_code')->nullable()->index();
                }
                if (! Schema::hasColumn('users', 'notify_new_listings')) {
                    $table->boolean('notify_new_listings')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('market_listings', 'rejection_reason')) {
            Schema::table('market_listings', fn (Blueprint $t) => $t->dropColumn('rejection_reason'));
        }
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['telegram_chat_id', 'telegram_link_code', 'notify_new_listings'] as $c) {
                    if (Schema::hasColumn('users', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
