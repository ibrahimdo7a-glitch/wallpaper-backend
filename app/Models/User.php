<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar',
        'bio_ar',
        'bio_en',
        'website',
        'twitter',
        'is_active',
        'force_password_change',
        'daily_upload_limit',
        'auto_publish',
        'max_file_size_mb',
        'can_upload_without_watermark',
        'show_public_profile',
        'telegram_chat_id',
        'telegram_link_code',
        'notify_new_listings',
        'pending_reject_listing_id',
        'last_login_at',
        'last_login_ip_hash',
        'failed_login_attempts',
        'locked_until',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'last_login_ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'force_password_change' => 'boolean',
            'auto_publish' => 'boolean',
            'can_upload_without_watermark' => 'boolean',
            'show_public_profile' => 'boolean',
            'notify_new_listings' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasPermissionTo('can_login_admin_panel');
    }

    public function wallpapers(): HasMany
    {
        return $this->hasMany(Wallpaper::class, 'uploaded_by');
    }

    public function approvedWallpapers(): HasMany
    {
        return $this->hasMany(Wallpaper::class, 'approved_by');
    }

    public function allowedCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'user_allowed_categories');
    }

    public function allowedWatermarks(): BelongsToMany
    {
        return $this->belongsToMany(Watermark::class, 'watermark_user');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'username', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('user');
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getDailyUploadCount(): int
    {
        return $this->wallpapers()
            ->whereDate('created_at', today())
            ->count();
    }

    public function canUploadToday(): bool
    {
        return $this->getDailyUploadCount() < $this->daily_upload_limit;
    }

    public function getPublicStats(): array
    {
        return [
            'wallpapers_count' => $this->wallpapers()->published()->count(),
            'downloads_count' => $this->wallpapers()->published()->sum('downloads_count'),
            'likes_count' => $this->wallpapers()->published()->sum('likes_count'),
            'views_count' => $this->wallpapers()->published()->sum('views_count'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
