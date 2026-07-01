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

    /**
     * Lightweight HTML hardening for stored article content — strips script/iframe/
     * event-handlers/js-URIs. Not a full sanitizer (HTMLPurifier is the long-term
     * fix) but closes the practical XSS vectors in AI/admin-authored HTML.
     */
    public static function sanitizeHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }
        $html = preg_replace('#<\s*(script|style|iframe|object|embed|form)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('#<\s*/?\s*(script|style|iframe|object|embed|form)\b[^>]*>#is', '', $html) ?? $html;
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        $html = preg_replace('#\b(href|src)\s*=\s*("|\')\s*(?:javascript|data)\s*:[^"\']*\2#i', '$1=$2#$2', $html) ?? $html;
        return $html;
    }

    protected static function booted(): void
    {
        static::creating(function (self $article) {
            if (empty($article->slug)) {
                $base  = $article->title_en ?? $article->title_ar ?? 'news';
                $latin = Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', (string) $base));
                $article->slug = ($latin ?: 'news') . '-' . Str::random(6);
            }
        });

        // Stamp the publish time the moment an article becomes published — on create OR
        // when a draft is published from the Edit form (the creating hook missed that path,
        // leaving published_at null and breaking newest-first ordering).
        static::saving(function (self $article) {
            if ($article->status === 'published' && empty($article->published_at)) {
                $article->published_at = now();
            }
            // Harden rich HTML (defense against XSS in AI-generated / admin content).
            $article->content_ar = static::sanitizeHtml($article->content_ar);
            $article->content_en = static::sanitizeHtml($article->content_en);
        });

        static::saved(function (self $article) {
            if ($article->news_category_id) {
                NewsCategory::where('id', $article->news_category_id)->update([
                    'articles_count' => static::where('news_category_id', $article->news_category_id)
                        ->where('status', 'published')->count(),
                ]);
            }

            // DM subscribed members in Telegram — once, on first publish.
            if ($article->status === 'published' && ! $article->member_notified) {
                \Illuminate\Support\Facades\DB::table('news_articles')->where('id', $article->id)->update(['member_notified' => true]);
                $id = $article->id;
                dispatch(function () use ($id) {
                    try {
                        $a = static::find($id);
                        if (! $a) {
                            return;
                        }
                        $caption = $a->telegramCaption();
                        $tg = app(\App\Services\TelegramService::class);
                        \App\Models\Member::where('news_telegram', true)->where('status', 'active')
                            ->whereNotNull('telegram_id')
                            ->select('telegram_id')->chunk(50, function ($members) use ($tg, $caption) {
                                foreach ($members as $m) {
                                    $tg->sendMessage((string) $m->telegram_id, $caption);
                                    usleep(40000); // ~25/sec, under Telegram limits
                                }
                            });
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('news member notify failed: ' . $e->getMessage());
                    }
                })->afterResponse();
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
