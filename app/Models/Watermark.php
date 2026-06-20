<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Watermark extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'type', 'text_ar', 'text_en', 'image_file',
        'font_family', 'font_size', 'font_color', 'opacity',
        'position', 'margin_x', 'margin_y', 'rotation', 'scale',
        'is_active', 'is_default', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'font_size' => 'integer',
            'opacity' => 'integer',
            'margin_x' => 'integer',
            'margin_y' => 'integer',
            'rotation' => 'integer',
            'scale' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wallpapers(): HasMany
    {
        return $this->hasMany(Wallpaper::class);
    }

    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'watermark_user');
    }

    public function allowedRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            'watermark_role'
        );
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_file) {
            return null;
        }

        return Storage::disk('r2')->url($this->image_file);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getDefault(): ?self
    {
        return static::where('is_active', true)->where('is_default', true)->first();
    }

    public function setAsDefault(): void
    {
        static::where('is_default', true)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
