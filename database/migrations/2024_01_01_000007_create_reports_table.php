<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallpaper_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', [
                'copyright',
                'inappropriate',
                'spam',
                'offensive',
                'other'
            ]);
            $table->text('message')->nullable();
            $table->string('ip_hash', 64);
            $table->enum('status', ['new', 'reviewed', 'dismissed'])->default('new');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('wallpaper_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
