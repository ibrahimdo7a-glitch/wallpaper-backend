<?php

namespace App\Filament\Pages;

use App\Models\NewsArticle;
use App\Services\AiService;
use App\Services\NewsFetchService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FetchNewsPage extends Page
{
    use \App\Filament\Concerns\HiddenFromCreatives;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-down-on-square-stack';
    protected static ?string $navigationGroup = 'الأخبار';
    protected static ?int    $navigationSort  = 9;

    protected static string $view = 'filament.pages.fetch-news';

    /** Fetched headlines (['title','link','source_name','when','image','ts']). */
    public array $items = [];

    /** Checked article links (bound to the checkboxes in the view). */
    public array $selected = [];

    /** Just-generated drafts to open for review (['title','url']). */
    public array $generated = [];

    public function getTitle(): string|Htmlable { return 'جلب الأخبار'; }
    public static function getNavigationLabel(): string { return 'جلب الأخبار'; }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetch')
                ->label('جلب آخر الأخبار')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('fetchNews'),

            Action::make('generate')
                ->label('توليد المقالات المحددة')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('سيفتح الذكاء كل رابط محدد، يلخّص ويترجم ويملأ الحقول، ويحفظه كمسودة. قد يأخذ دقيقة لكل عدة أخبار.')
                ->action('generateSelected'),
        ];
    }

    public function fetchNews(): void
    {
        $this->generated = [];
        $items = app(NewsFetchService::class)->fetchLatest(15, 30);
        $this->items = array_map(function ($i) {
            $i['when'] = ! empty($i['ts']) ? Carbon::createFromTimestamp($i['ts'])->diffForHumans() : '';
            return $i;
        }, $items);
        $this->selected = [];

        if (empty($this->items)) {
            Notification::make()
                ->title('ما قدرت أجلب أي خبر')
                ->body('تأكد أنك أضفت مصادر مفعّلة بروابط صحيحة في «مصادر الأخبار».')
                ->warning()->send();
            return;
        }

        Notification::make()->title('تم جلب ' . count($this->items) . ' خبر — افتح العنوان لتقرأه، وأشّر اللي تبيه')->success()->send();
    }

    public function toggleAll(): void
    {
        $links = array_column($this->items, 'link');
        $this->selected = count($this->selected) >= count($links) ? [] : $links;
    }

    public function generateSelected(): void
    {
        $ai = app(AiService::class);
        if (! $ai->isConfigured()) {
            Notification::make()->title('فعّل الذكاء وأضف مفتاح API في إعدادات الموقع أولًا')->danger()->send();
            return;
        }

        $selected = $this->selected;
        if (empty($selected)) {
            Notification::make()->title('أشّر خبرًا واحدًا على الأقل')->warning()->send();
            return;
        }

        $generated = []; $fail = 0;
        foreach ($selected as $link) {
            $item = collect($this->items)->firstWhere('link', $link) ?? [];
            try {
                $r = $ai->articleFromUrl($link);
                if (! $r || empty($r['title_ar'])) {
                    $fail++;
                    continue;
                }

                $article = new NewsArticle();
                $article->fill([
                    'title_ar'   => $r['title_ar'],
                    'title_en'   => $r['title_en']   ?? null,
                    'summary_ar' => $r['summary_ar'] ?? null,
                    'summary_en' => $r['summary_en'] ?? null,
                    'content_ar' => $r['content_ar'] ?? null,
                    'content_en' => $r['content_en'] ?? null,
                    'source_url'  => $link,
                    'source_name' => $item['source_name'] ?? null,
                    'status'      => 'draft',
                ]);

                if (! empty($item['image'])) {
                    if ($cover = $this->downloadCover($item['image'])) {
                        $article->cover_image = $cover;
                    }
                }

                $article->save();
                $generated[] = [
                    'title' => $article->title_ar,
                    'url'   => \App\Filament\Resources\NewsArticleResource::getUrl('edit', ['record' => $article]),
                ];
            } catch (\Throwable) {
                $fail++;
            }
        }

        // Drop processed items from the list so the page reflects what's left.
        $this->items = collect($this->items)->reject(fn ($i) => in_array($i['link'], $selected, true))->values()->all();
        $this->selected  = [];
        $this->generated = $generated;

        Notification::make()
            ->title('تم توليد ' . count($generated) . ' مقال' . ($fail ? " · تعذّر {$fail}" : ''))
            ->body('افتح كل مقال من الأعلى للمراجعة والنشر.')
            ->success()->send();
    }

    private function downloadCover(string $url): ?string
    {
        try {
            $resp = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; QEVBot/1.0)'])
                ->get($url);
            if (! $resp->successful()) {
                return null;
            }
            $ct  = (string) $resp->header('Content-Type');
            $ext = match (true) {
                str_contains($ct, 'png')  => 'png',
                str_contains($ct, 'webp') => 'webp',
                str_contains($ct, 'gif')  => 'gif',
                default                   => 'jpg',
            };
            $path = 'news/covers/' . Str::random(24) . '.' . $ext;
            Storage::disk(config('filesystems.default', 'public'))->put($path, $resp->body(), 'private');
            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
