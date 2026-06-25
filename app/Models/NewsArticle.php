<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsArticle extends Model
{
    use SoftDeletes;

    protected $table = 'news_articles';

    protected $fillable = [
        'news_category_id', 'title_ar', 'title_en', 'slug',
        'summary_ar', 'summary_en', 'content_ar', 'content_en',
        'cover_image', 'source_url', 'source_name', 'author_name',
        'status', 'is_featured', 'is_breaking',
        'views_count', 'shares_count',
        'meta_title_ar', 'meta_title_en', 'meta_description_ar', 'meta_description_en',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured'  => 'boolean',
            'is_breaking'  => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $article) {
            if (empty($article->slug)) {
                $base = $article->title_en ?? $article->title_ar ?? 'news';
                $article->slug = Str::slug($base) . '-' . Str::random(6);
            }
            if (empty($article->published_at) && $article->status === 'published') {
                $article->published_at = now();
            }
        });

        static::saved(function (self $article) {
            if ($article->news_category_id) {
                NewsCategory::where('id', $article->news_category_id)->update([
                    'articles_count' => static::where('news_category_id', $article->news_category_id)
                        ->where('status', 'published')->count(),
                ]);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'news_article_brand');
    }

    public function carModels(): BelongsToMany
    {
        return $this->belongsToMany(CarModel::class, 'news_article_car_model');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image) return null;
        return Storage::disk(config('filesystems.default', 'public'))->url($this->cover_image);
    }

    /** Formatted caption for a Telegram news post (HTML). */
    public function telegramCaption(): string
    {
        $front = rtrim(config('app.frontend_url', 'https://qev.app'), '/');
        $esc   = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $lines = ['<b>' . $esc($this->title_ar) . '</b>'];

        if (filled($this->summary_ar)) {
            $lines[] = '';
            $lines[] = $esc(Str::limit($this->summary_ar, 320));
        }

        $lines[] = '';
        $lines[] = '📰 <a href="' . $esc("{$front}/ar/news/{$this->slug}") . '">اقرأ الخبر كاملًا</a>';
        $lines[] = '📲 <a href="' . $esc("{$front}/ar/news") . '">المزيد من الأخبار</a>';

        return implode("\n", $lines);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
