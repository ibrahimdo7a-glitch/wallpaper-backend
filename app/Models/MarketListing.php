<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketListing extends Model
{
    use SoftDeletes;

    /** Canonical country list — drives the admin form picker and the public market filter. */
    public const COUNTRIES = ['قطر', 'السعودية', 'الإمارات', 'الكويت', 'البحرين', 'عُمان'];

    protected $fillable = [
        'listing_type', 'market_category_id', 'title_ar', 'title_en', 'slug',
        'description_ar', 'description_en', 'price', 'currency', 'is_negotiable',
        'condition', 'country', 'city', 'brand_id', 'car_model_id', 'custom_brand', 'custom_model', 'year', 'mileage',
        'specs', 'images', 'contact_name', 'contact_phone', 'contact_whatsapp', 'contact_telegram',
        'is_paid_listing', 'is_featured', 'status', 'rejection_reason', 'source', 'member_id', 'views_count', 'published_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'specs'           => 'array',
            'images'          => 'array',
            'is_negotiable'   => 'boolean',
            'is_paid_listing' => 'boolean',
            'is_featured'     => 'boolean',
            'published_at'    => 'datetime',
            'expires_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $l) {
            if (empty($l->slug)) {
                $base = $l->title_en ?: $l->title_ar ?: 'listing';
                $slug = Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $base));
                $l->slug = ($slug ?: 'listing') . '-' . Str::random(6);
            }
            if (empty($l->published_at) && $l->status === 'published') {
                $l->published_at = now();
            }
        });

        // Whenever a member listing transitions to "rejected" — from the status dropdown,
        // the table action, or a Telegram tap — DM the member the reason once.
        static::saved(function (self $l) {
            if ($l->wasChanged('status') && $l->status === 'rejected' && $l->member_id) {
                try {
                    app(\App\Services\TelegramService::class)
                        ->notifyListingRejected($l->loadMissing('member'), (string) $l->rejection_reason);
                } catch (\Throwable) {
                    // never let a Telegram failure break the save
                }
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(MarketCategory::class, 'market_category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function carModel()
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    /** First image as a full URL (used as the cover). */
    public function getCoverUrlAttribute(): ?string
    {
        $first = is_array($this->images) ? ($this->images[0] ?? null) : null;
        return $first ? Storage::disk(config('filesystems.default', 'public'))->url($first) : null;
    }

    /** All images as full URLs. */
    public function imageUrls(): array
    {
        if (! is_array($this->images)) {
            return [];
        }
        $disk = Storage::disk(config('filesystems.default', 'public'));
        return array_values(array_map(fn ($p) => $disk->url($p), $this->images));
    }

    public static function types(): array
    {
        return [
            'part'        => '🔧 قطع غيار',
            'accessory'   => '🎁 اكسسوارات',
            'car_sale'    => '🚗 سيارة للبيع',
            'car_request' => '🔎 طلب سيارة',
            'service'     => '🛠️ خدمة',
        ];
    }
}
