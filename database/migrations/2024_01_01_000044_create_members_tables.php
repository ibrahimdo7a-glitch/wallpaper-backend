<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Public members — fully separate from admin `users`. Identity comes from Telegram.
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('telegram_username')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');     // active | banned
            $table->string('tier')->default('none');         // none | premium | merchant (phase 2)
            $table->boolean('is_premium')->default(false);   // phase 2
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // One-time login sessions: site creates a token, the bot verifies it on /start.
        Schema::create('member_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->unsignedBigInteger('telegram_id')->nullable();
            $table->string('status')->default('pending');    // pending | verified | used
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_login_tokens');
        Schema::dropIfExists('members');
    }
};
