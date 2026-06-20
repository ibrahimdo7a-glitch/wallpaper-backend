<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallpaper_id')->constrained()->cascadeOnDelete();
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->string('cookie_id', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->timestamps();

            $table->unique(['wallpaper_id', 'ip_hash', 'user_agent_hash'], 'likes_unique');
            $table->index('wallpaper_id');
            $table->index('created_at');
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallpaper_id')->constrained()->cascadeOnDelete();
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->string('cookie_id', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('resolution')->nullable();
            $table->timestamps();

            $table->index('wallpaper_id');
            $table->index('created_at');
            $table->index(['ip_hash', 'created_at']);
        });

        Schema::create('views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallpaper_id')->constrained()->cascadeOnDelete();
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->string('country_code', 2)->nullable();
            $table->timestamp('created_at');

            $table->index('wallpaper_id');
            $table->index('created_at');
            $table->index(['wallpaper_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('views');
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('likes');
    }
};
