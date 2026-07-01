<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('admin_login_logs')) {
            Schema::create('admin_login_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('email')->nullable();
                $table->string('event', 30)->index(); // password_failed | otp_sent | otp_success | otp_failed | otp_locked | login_no_2fa
                $table->string('ip', 45)->nullable();
                $table->char('country', 2)->nullable();
                $table->string('device', 10)->nullable();
                $table->string('os', 40)->nullable();
                $table->string('browser', 40)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_login_logs');
    }
};
