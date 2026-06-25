<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AndroidApp extends Model
{
    use SoftDeletes;

    protected $table = 'apps';

    protected $fillable = [
        'app_category_id', 'brand_id', 'car_model_id',
        'title_ar', 'title_en', 'slug',
        'short_description_ar', 'short_description_en',
        'badge_text_ar', 'badge_text_en',
        'description_ar', 'description_en',
        'icon_file', 'cover_image', 'apk_file', 'apk_sha256',
        'external_url', 'play_store_url', 'official_website_url',
        'version', 'package_name', 'developer', 'developer_name',
        'min_android', 'file_size', 'language',
        'requires_internet', 'requires_login', 'works_on_car_screen', 'tested_on_car',
        'safety_status', 'status',
        'is_featured', 'is_important', 'is_recommended', 'is_verified', 'show_on_home', 'is_free',
        'downloads_count', 'views_count', 'rating_average', 'rating_count',
        'published_at',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
    ];

    protected function casts(): array
    {
        return [
            'is_featured'        => 'boolean',
            'is_important'       => 'boolean',
            'is_recommended'     => 'boolean',
            'is_verified'        => 'boolean',
            'show_on_home'       => 'boolean',
            'is_free'            => 'boolean',
            'requires_internet'  => 'boolean',
            'requires_login'     => 'boolean',
            'works_on_car_screen'=> 'boolean',
            'tested_on_car'      => 'boolean',
            'published_at'       => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $app) {
            if (empty($app->slug)) {
                $base = $app->title_en ?? $app->title_ar ?? 'app';
                $app->slug = Str::slug($base) . '-' . Str::random(6);
            }
            if (empty($app->published_at) && $app->status === 'published') {
                $app->published_at = now();
            }
            // Column is NOT NULL on the live DB; the form doesn't set it.
            if (empty($app->language)) {
                $app->language = 'ar';
            }
        });

        static::saved(function (self $app) {
            $app->carModel?->refreshCounts();
            $app->brand?->refreshCounts();
        });
    }

    public function appCategory(): BelongsTo
    {
        return $this->belongsTo(AppCategory::class, 'app_category_id');
    }

    /** Alias used by the admin table/filters and the public API. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AppCategory::class, 'app_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    public function installationSteps(): HasMany
    {
        return $this->hasMany(AppInstallationStep::class, 'app_id')->orderBy('step_number');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(AppScreenshot::class, 'app_id')->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AppVersion::class, 'app_id')->orderByDesc('release_date');
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(AppCompatibility::class, 'app_id');
    }

    public function importantForModels(): BelongsToMany
    {
        return $this->belongsToMany(CarModel::class, 'car_model_important_apps', 'app_id', 'car_model_id')
                    ->withPivot('sort_order');
    }

    public function getIconUrlAttribute(): ?string
    {
        if (! $this->icon_file) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->icon_file);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->cover_image);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->apk_file) {
            return Storage::disk(config('filesystems.default', 'public'))->url($this->apk_file);
        }
        return $this->play_store_url ?? $this->external_url;
    }

    /** Formatted caption for a Telegram app post (HTML). */
    public function telegramCaption(): string
    {
        $front = rtrim(config('app.frontend_url', 'https://qev.app'), '/');
        $esc   = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $lines = ['<b>' . $esc($this->title_ar) . '</b>'];

        // The explanation the admin wrote — keep line breaks so bullet points stay as separate lines.
        $desc = $this->short_description_ar ?: $this->description_ar;
        if (filled($desc)) {
            $lines[] = '';
            $lines[] = $esc(\Illuminate\Support\Str::limit(trim(strip_tags($desc)), 500));
        }

        // Auto feature points (badge → free/paid → version → size → developer).
        $points = [];
        $badge = $this->badge_text_ar ?: ($this->works_on_car_screen ? 'يعمل على شاشة السيارة' : null);
        if (filled($badge))         { $points[] = '✅ ' . $esc($badge); }
        $points[] = $this->is_free ? '💰 مجاني' : '💰 مدفوع';
        if (filled($this->version)) { $points[] = '📦 الإصدار ' . $esc($this->version); }
        if ($this->file_size)       { $points[] = '💾 الحجم ' . $esc($this->file_size_label); }
        $dev = $this->developer ?: $this->developer_name;
        if (filled($dev))           { $points[] = '👨‍💻 ' . $esc($dev); }
        if (! empty($points)) {
            $lines[] = '';
            $lines = array_merge($lines, array_slice($points, 0, 5));
        }

        // Direct download + page links.
        $lines[] = '';
        if (filled($this->download_url)) {
            $lines[] = '⬇️ <b>تحميل مباشر:</b> <a href="' . $esc($this->download_url) . '">اضغط هنا</a>';
        }
        $lines[] = '📄 <a href="' . $esc("{$front}/ar/apps/{$this->slug}") . '">التفاصيل ولقطات الشاشة</a>';
        $lines[] = '📲 <a href="' . $esc("{$front}/ar/apps") . '">المزيد من البرامج</a>';

        return implode("\n", $lines);
    }

    public function getTelegramPhotoAttribute(): ?string
    {
        return $this->cover_image_url ?: $this->icon_url;
    }

    public function getFileSizeLabelAttribute(): string
    {
        if (! $this->file_size) return '—';
        $mb = $this->file_size / 1024 / 1024;
        return $mb >= 1 ? round($mb, 1) . ' MB' : round($this->file_size / 1024) . ' KB';
    }

    public function getSafetyBadgeAttribute(): array
    {
        return match($this->safety_status) {
            'verified'        => ['label_ar' => 'موثق',        'label_en' => 'Verified',        'color' => 'green'],
            'tested'          => ['label_ar' => 'مجرب',        'label_en' => 'Tested',          'color' => 'blue'],
            'external_source' => ['label_ar' => 'مصدر خارجي', 'label_en' => 'External Source', 'color' => 'yellow'],
            default           => ['label_ar' => 'غير مجرب',   'label_en' => 'Not Tested',      'color' => 'gray'],
        };
    }

    public function scopePublished($query) { return $query->where('status', 'published'); }
    public function scopeImportant($query) { return $query->where('is_important', true); }
    public function scopeFeatured($query)  { return $query->where('is_featured', true); }
}
