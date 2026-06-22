<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AppScreenshot extends Model
{
    protected $table = 'app_screenshots';

    protected $fillable = ['app_id', 'image_file', 'caption_ar', 'caption_en', 'sort_order'];

    public function app(): BelongsTo { return $this->belongsTo(AndroidApp::class, 'app_id'); }

    public function getImageUrlAttribute(): string
    {
        return Storage::disk(config('filesystems.default', 'public'))->url($this->image_file);
    }
}
