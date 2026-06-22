<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class NewsSubscription extends Model
{
    protected $table = 'news_subscriptions';

    protected $fillable = [
        'email', 'name', 'token', 'is_verified', 'subscribe_all',
        'status', 'verified_at', 'unsubscribed_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'     => 'boolean',
            'subscribe_all'   => 'boolean',
            'verified_at'     => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sub) {
            if (empty($sub->token)) {
                $sub->token = Str::random(64);
            }
        });
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'subscription_brands', 'news_subscription_id', 'brand_id');
    }

    public function carModels(): BelongsToMany
    {
        return $this->belongsToMany(CarModel::class, 'subscription_car_models', 'news_subscription_id', 'car_model_id');
    }

    public function newsCategories(): BelongsToMany
    {
        return $this->belongsToMany(NewsCategory::class, 'subscription_news_categories', 'news_subscription_id', 'news_category_id');
    }

    public function unsubscribe(): void
    {
        $this->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
    }

    public function scopeActive($query) { return $query->where('status', 'active'); }
}
