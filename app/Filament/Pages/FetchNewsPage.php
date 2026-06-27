<?php

namespace App\Filament\Pages;

use App\Models\NewsArticle;
use App\Services\AiService;
use App\Services\NewsFetchService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FetchNewsPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;
    use \App\Filament\Concerns\HiddenFromCreatives;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-down-on-square-stack';
    protected static ?string $navigationGroup = 'الأخبار';
    protected static ?int    $navigationSort  = 9;

    protected static string $view = 'filament.pages.fetch-news';

    /** Fetched headlines (index => ['title','link','source_name','published_at','image','ts']). */
    public array $items = [];

    /** Form state — holds the checked article links under 'selected'. */
    public ?array $data = [];

    public function getTitle(): string|Htmlable { return 'جلب الأخبار'; }
    public static function getNavigationLabel(): string { return 'جلب الأخبار'; }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\CheckboxList::make('selected')
                    ->label('الأخبار المجلوبة — أشّر اللي تبي تحوّله إلى مقالات')
                    ->options(fn () => $this->itemOptions())
                    ->descriptions(fn () => $this->itemDescriptions())
                    ->bulkToggleable()
                    ->columns(1)
                    ->hidden(fn () => empty($this->items)),
            ])
            ->statePath('data');
    }

    protected function itemOptions(): array
    {
        $opts = [];
        foreach ($this->items as $i) {
            $opts[$i['link']] = $i['title'] ?: $i['link'];
        }
        return $opts;
    }

    protected function itemDescriptions(): array
    {
        $desc = [];
        foreach ($this->items as $i) {
            $date = $i['ts'] ? \Illuminate\Support\Carbon::createFromTimestamp($i['ts'])->diffForHumans() : '';
            $desc[$i['link']] = trim('📰 ' . ($i['source_name'] ?? '') . ($date ? ' · ' . $date : ''));
        }
        return $desc;
    }

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
        $this->items = app(NewsFetchService::class)->fetchLatest(5);
        $this->data['selected'] = [];

        if (empty($this->items)) {
            Notification::make()
                ->title('ما قدرت أجلب أي خبر')
                ->body('تأكد أنك أضفت مصادر مفعّلة بروابط صحيحة في «مصادر الأخبار».')
                ->warning()->send();
            return;
        }

        Notification::make()->title('تم جلب ' . count($this->items) . ' خبر — أشّر اللي تبيه')->success()->send();
    }

    public function generateSelected(): void
    {
        $ai = app(AiService::class);
        if (! $ai->isConfigured()) {
            Notification::make()->title('فعّل الذكاء وأضف مفتاح API في إعدادات الموقع أولًا')->danger()->send();
            return;
        }

        $selected = $this->data['selected'] ?? [];
        if (empty($selected)) {
            Notification::make()->title('أشّر خبرًا واحدًا على الأقل')->warning()->send();
            return;
        }

        $ok = 0; $fail = 0;
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
                $ok++;
            } catch (\Throwable) {
                $fail++;
            }
        }

        // Drop processed items from the list so the page reflects what's left.
        $this->items = collect($this->items)->reject(fn ($i) => in_array($i['link'], $selected, true))->values()->all();
        $this->data['selected'] = [];

        Notification::make()
            ->title("تم إنشاء {$ok} مسودة" . ($fail ? " · تعذّر {$fail}" : ''))
            ->body('راجع المسودات من «الأخبار»، اضبط الصورة، ثم انشر.')
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
