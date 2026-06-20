<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Translation extends Model
{
    protected $fillable = ['key', 'value_ar', 'value_en', 'group', 'description'];

    public static function get(string $key, string $locale = null, string $default = ''): string
    {
        $locale ??= app()->getLocale();
        $cacheKey = "translation.{$locale}.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $locale, $default) {
            $translation = static::where('key', $key)->first();

            if (! $translation) {
                return $default ?: $key;
            }

            return $locale === 'ar' ? ($translation->value_ar ?: $default) : ($translation->value_en ?: $default);
        });
    }

    public static function clearCache(string $key = null): void
    {
        if ($key) {
            Cache::forget("translation.ar.{$key}");
            Cache::forget("translation.en.{$key}");
        } else {
            Cache::flush();
        }
    }

    protected static function booted(): void
    {
        static::saved(function (Translation $translation) {
            static::clearCache($translation->key);
        });
    }
}
