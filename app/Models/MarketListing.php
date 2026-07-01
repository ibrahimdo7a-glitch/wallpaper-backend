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
            'is_negotiable'        => 'boolean',
            'is_paid_listing'      => 'boolean',
            'is_featured'          => 'boolean',
            'subscribers_notified' => 'boolean',
            'published_at'         => 'datetime',
            'expires_at'           => 'datetime',
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

        // On first publish, DM every member subscribed to this channel (cars/parts) —
        // either to all listings or to this listing's brand. Once-only (subscribers_notified),
        // dispatched after the response, throttled under Telegram's limits.
        static::saved(function (self $l) {
            if ($l->status !== 'published' || $l->subscribers_notified || ! $l->subscriptionChannel()) {
                return;
            }

            \Illuminate\Support\Facades\DB::table('market_listings')
                ->where('id', $l->id)->update(['subscribers_notified' => true]);

            $id = $l->id;
            dispatch(function () use ($id) {
                try {
                    $l = static::find($id);
                    if (! $l || ! ($channel = $l->subscriptionChannel())) {
                        return;
                    }
                    $caption = $l->telegramSubscriberCaption();
                    $tg = app(\App\Services\TelegramService::class);

                    $memberIds = \App\Models\MemberSubscription::where('channel', $channel)
                        ->where(function ($q) use ($l) {
                            $q->whereNull('brand_id');
                            if ($l->brand_id) {
                                $q->orWhere('brand_id', $l->brand_id);
                            }
                        })
                        ->pluck('member_id')->unique();

                    if ($memberIds->isEmpty()) {
                        return;
                    }

                    \App\Models\Member::whereIn('id', $memberIds)
                        ->where('status', 'active')->whereNotNull('telegram_id')
                        ->select('telegram_id')->chunk(50, function ($members) use ($tg, $caption) {
                            foreach ($members as $m) {
                                $tg->sendMessage((string) $m->telegram_id, $caption);
                                usleep(40000); // ~25/sec, under Telegram limits
                            }
                        });
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('listing subscriber notify failed: ' . $e->getMessage());
                }
            })->afterResponse();
        });
    }

    /** Which subscription channel this listing belongs to (null = not notifiable). */
    public function subscriptionChannel(): ?string
    {
        return match ($this->listing_type) {
            'car_sale', 'car_request' => 'cars',
            'part', 'accessory'       => 'parts',
            default                   => null,
        };
    }

    /** DM body sent to subscribers when this listing goes live. */
    public function telegramSubscriberCaption(): string
    {
        $front = rtrim(config('app.frontend_url', 'https://qev.app'), '/');
        $price = $this->price !== null
            ? number_format((float) $this->price, 0) . ' ' . $this->currency
            : 'حسب الطلب';
        $loc  = trim(implode('، ', array_filter([$this->city, $this->country])));
        $head = match ($this->listing_type) {
            'car_sale'    => '🚗 سيارة جديدة للبيع',
            'car_request' => '🔎 طلب سيارة جديد',
            'part'        => '🔧 قطعة غيار جديدة',
            'accessory'   => '🎁 إكسسوار جديد',
            default       => '🛒 إعلان جديد',
        };

        $lines = [
            "<b>{$head}</b>",
            '«' . e($this->title_ar) . '»',
            '💰 ' . e($price) . ($this->is_negotiable ? ' • قابل للتفاوض' : ''),
        ];
        if ($loc) {
            $lines[] = '📍 ' . e($loc);
        }
        $lines[] = '';
        $lines[] = '🔗 <a href="' . e("{$front}/ar/market/{$this->slug}") . '">تفاصيل الإعلان</a>';
        $lines[] = '⚙️ <a href="' . e("{$front}/ar/account") . '">إدارة اشتراكاتك</a>';

        return implode("\n", $lines);
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
