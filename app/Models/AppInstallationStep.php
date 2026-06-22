<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AppInstallationStep extends Model
{
    protected $table = 'app_installation_steps';

    protected $fillable = [
        'app_id', 'step_number', 'image_file', 'title_ar', 'title_en',
    ];

    protected function casts(): array
    {
        return ['step_number' => 'integer'];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(AndroidApp::class, 'app_id');
    }

    public function getImageUrlAttribute(): string
    {
        return Storage::disk(config('filesystems.default', 'public'))->url($this->image_file);
    }
}
