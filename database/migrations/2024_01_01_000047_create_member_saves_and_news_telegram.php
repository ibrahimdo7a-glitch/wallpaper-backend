<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic-ish saves: a member bookmarks a listing/content/news item.
        Schema::create('member_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('type');          // listing | content | news
            $table->unsignedBigInteger('item_id');
            $table->timestamps();
            $table->unique(['member_id', 'type', 'item_id']);
            $table->index(['type', 'item_id']);
        });

        // Member opt-in to receive news in Telegram.
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('news_telegram')->default(false)->after('is_premium');
        });

        // So each article notifies subscribed members only once.
        Schema::table('news_articles', function (Blueprint $table) {
            $table->boolean('member_notified')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_saves');
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('news_telegram');
        });
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn('member_notified');
        });
    }
};
