<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watermarks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['text', 'image', 'combined'])->default('text');
            $table->string('text_ar')->nullable();
            $table->string('text_en')->nullable();
            $table->string('image_file')->nullable();
            $table->string('font_family')->default('Arial');
            $table->integer('font_size')->default(24);
            $table->string('font_color')->default('#FFFFFF');
            $table->integer('opacity')->default(70);
            $table->enum('position', [
                'top-left', 'top-center', 'top-right',
                'middle-left', 'center', 'middle-right',
                'bottom-left', 'bottom-center', 'bottom-right',
                'custom'
            ])->default('bottom-right');
            $table->integer('margin_x')->default(20);
            $table->integer('margin_y')->default(20);
            $table->integer('rotation')->default(0);
            $table->integer('scale')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_default');
        });

        Schema::create('watermark_role', function (Blueprint $table) {
            $table->foreignId('watermark_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->primary(['watermark_id', 'role_id']);
        });

        Schema::create('watermark_user', function (Blueprint $table) {
            $table->foreignId('watermark_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['watermark_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watermark_user');
        Schema::dropIfExists('watermark_role');
        Schema::dropIfExists('watermarks');
    }
};
