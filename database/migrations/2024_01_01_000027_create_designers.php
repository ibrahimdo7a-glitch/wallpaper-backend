<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designers', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('avatar_path')->nullable();
            $table->text('bio_ar')->nullable();
            $table->string('telegram_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('content_items', function (Blueprint $table) {
            $table->foreignId('designer_id')->nullable()->after('author_name')
                ->constrained('designers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designer_id');
        });
        Schema::dropIfExists('designers');
    }
};
