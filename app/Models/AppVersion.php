<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AppVersion extends Model
{
    protected $table = 'app_versions';

    protected $fillable = [
        'app_id', 'version', 'apk_file', 'apk_sha256', 'file_size',
        'changelog_ar', 'changelog_en', 'release_date', 'is_stable',
    ];

    protected function casts(): array
    {
        return [
            'is_stable'    => 'boolean',
            'release_date' => 'date',
        ];
    }

    public function app(): BelongsTo { return $this->belongsTo(AndroidApp::class, 'app_id'); }

    public function getApkUrlAttribute(): ?string
    {
        if (! $this->apk_file) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->apk_file);
    }

    public function getFileSizeLabelAttribute(): string
    {
        if (! $this->file_size) return '—';
        $mb = $this->file_size / 1024 / 1024;
        return $mb >= 1 ? round($mb, 1) . ' MB' : round($this->file_size / 1024) . ' KB';
    }
}
