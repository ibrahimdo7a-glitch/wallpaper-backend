<?php

namespace App\Filament\Pages;

use App\Models\AdminLoginLog;
use App\Models\AndroidApp;
use App\Models\ContactMessage;
use App\Models\ContentItem;
use App\Models\MarketListing;
use App\Models\Member;
use App\Models\NewsArticle;
use App\Models\Report;
use App\Services\AnalyticsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Operations room — the admin home. Answers "what needs my attention now?":
 * live pulse + system health, an urgent-task queue, quick launch, an activity
 * feed, and (super admin only) a security/login pulse. Charts moved to الإحصائيات.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'لوحة التحكم';
    protected static string $view = 'filament.pages.ops-room';

    public array $ops = [];
    public bool $isSuper = false;

    /** No default Filament widgets — this page renders its own operations view. */
    public function getWidgets(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->build();
    }

    public function refreshOps(): void
    {
        $this->build();
    }

    private function build(): void
    {
        $this->isSuper = auth()->user()?->hasRole('super_admin') ?? false;
        $svc = app(AnalyticsService::class);

        $this->ops = [
            'pulse'         => $this->pulse($svc),
            'tasks'         => $this->tasks(),
            'launch'        => $this->launch(),
            'activity'      => $this->activity(),
            'logins'        => $this->isSuper ? $this->recentLogins() : [],
            'failed_logins' => $this->isSuper ? $this->failedLogins24h() : 0,
        ];
    }

    // ─── live pulse + system health ──────────────────────────────────────────

    private function pulse(AnalyticsService $svc): array
    {
        $online = ['total' => 0, 'members' => 0, 'guests' => 0];
        $visitorsToday = 0;
        $registrationsToday = 0;
        try {
            $online = $svc->online();
            $visitorsToday = $svc->visitorsBetween(now()->startOfDay());
            $registrationsToday = $svc->newMembersSince(now()->startOfDay());
        } catch (\Throwable) {
        }

        $system = [];
        try {
            DB::select('select 1');
            $system[] = ['label' => 'قاعدة البيانات', 'ok' => true, 'note' => 'متصلة'];
        } catch (\Throwable) {
            $system[] = ['label' => 'قاعدة البيانات', 'ok' => false, 'note' => 'فشل'];
        }
        $r2 = (bool) config('filesystems.disks.r2.key');
        $system[] = ['label' => 'التخزين R2', 'ok' => $r2, 'note' => $r2 ? 'مضبوط' : 'محلي'];

        $pending = $this->safeCount(fn () => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0);
        $system[] = ['label' => 'الطابور', 'ok' => $pending <= 50, 'note' => "{$pending} معلّقة"];

        $failed = $this->safeCount(fn () => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0);
        $system[] = ['label' => 'مهام فاشلة', 'ok' => $failed === 0, 'note' => (string) $failed];

        return [
            'online'               => $online,
            'visitors_today'       => $visitorsToday,
            'registrations_today'  => $registrationsToday,
            'system'               => $system,
        ];
    }

    // ─── urgent task queue ───────────────────────────────────────────────────

    private function tasks(): array
    {
        $out = [];

        $pendingListings = $this->safeCount(fn () => MarketListing::where('status', 'pending')->count());
        $out[] = ['icon' => '📋', 'label' => 'إعلانات بانتظار المراجعة', 'count' => $pendingListings,
            'url' => $this->url(\App\Filament\Resources\CarMarketListingResource::class)];

        $openReports = $this->safeCount(fn () => Schema::hasColumn('reports', 'status')
            ? Report::where('status', 'pending')->count() : Report::count());
        $out[] = ['icon' => '🚩', 'label' => 'بلاغات بحاجة معالجة', 'count' => $openReports,
            'url' => $this->url(\App\Filament\Resources\ReportResource::class)];

        $contactsToday = $this->safeCount(fn () => ContactMessage::where('created_at', '>=', now()->subDay())->count());
        $out[] = ['icon' => '📨', 'label' => 'رسائل تواصل (٢٤ ساعة)', 'count' => $contactsToday,
            'url' => $this->url(\App\Filament\Pages\SiteSettingsPage::class)];

        $draftNews = $this->safeCount(fn () => NewsArticle::where('status', 'draft')->count());
        $out[] = ['icon' => '📰', 'label' => 'أخبار مسودّة', 'count' => $draftNews,
            'url' => $this->url(\App\Filament\Resources\NewsArticleResource::class)];

        return $out;
    }

    // ─── quick launch grid ───────────────────────────────────────────────────

    private function launch(): array
    {
        $items = [
            ['label' => 'المشرفون', 'icon' => '👮', 'url' => $this->url(\App\Filament\Resources\UserResource::class), 'super' => true],
            ['label' => 'الأعضاء', 'icon' => '👥', 'url' => $this->url(\App\Filament\Resources\MemberResource::class), 'super' => true],
            ['label' => 'الأخبار', 'icon' => '📰', 'url' => $this->url(\App\Filament\Resources\NewsArticleResource::class), 'super' => false],
            ['label' => 'السوق', 'icon' => '🛒', 'url' => $this->url(\App\Filament\Resources\CarMarketListingResource::class), 'super' => false],
            ['label' => 'الخلفيات', 'icon' => '🖼️', 'url' => $this->url(\App\Filament\Resources\ContentItemResource::class), 'super' => false],
            ['label' => 'التطبيقات', 'icon' => '📱', 'url' => $this->url(\App\Filament\Resources\AndroidAppResource::class), 'super' => false],
            ['label' => 'الماركات', 'icon' => '🚗', 'url' => $this->url(\App\Filament\Resources\BrandResource::class), 'super' => false],
            ['label' => 'التحليلات', 'icon' => '📊', 'url' => $this->url(AnalyticsPage::class), 'super' => true],
            ['label' => 'الزوّار المباشرون', 'icon' => '📡', 'url' => $this->url(LiveVisitorsPage::class), 'super' => true],
            ['label' => 'فحص الموقع', 'icon' => '🩺', 'url' => $this->url(SiteHealthPage::class), 'super' => true],
            ['label' => 'إعدادات الموقع', 'icon' => '⚙️', 'url' => $this->url(SiteSettingsPage::class), 'super' => true],
        ];

        return array_values(array_filter($items, fn ($i) => $this->isSuper || ! $i['super']));
    }

    // ─── activity feed ───────────────────────────────────────────────────────

    private function activity(): array
    {
        $feed = [];
        try {
            if ($l = MarketListing::latest()->first(['title_ar', 'created_at'])) {
                $feed[] = ['icon' => '🛒', 'text' => 'إعلان جديد: ' . $l->title_ar, 'at' => $l->created_at];
            }
            if ($m = Member::latest()->first(['name', 'telegram_username', 'created_at'])) {
                $feed[] = ['icon' => '🧑', 'text' => 'عضو جديد: ' . ($m->name ?: '@' . $m->telegram_username), 'at' => $m->created_at];
            }
            if ($n = NewsArticle::latest()->first(['title_ar', 'created_at'])) {
                $feed[] = ['icon' => '📰', 'text' => 'خبر: ' . $n->title_ar, 'at' => $n->created_at];
            }
            if ($w = ContentItem::latest()->first(['title_ar', 'created_at'])) {
                $feed[] = ['icon' => '🖼️', 'text' => 'محتوى: ' . $w->title_ar, 'at' => $w->created_at];
            }
        } catch (\Throwable) {
        }

        usort($feed, fn ($a, $b) => ($b['at']?->timestamp ?? 0) <=> ($a['at']?->timestamp ?? 0));
        return $feed;
    }

    private function recentLogins(): array
    {
        try {
            return AdminLoginLog::latest('created_at')->limit(6)
                ->get(['email', 'event', 'ip', 'country', 'device', 'browser', 'created_at'])->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function failedLogins24h(): int
    {
        return $this->safeCount(fn () => AdminLoginLog::whereIn('event', ['password_failed', 'otp_failed', 'otp_locked', 'recovery_failed'])
            ->where('created_at', '>=', now()->subDay())->count());
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function url(string $class): string
    {
        try {
            return $class::getUrl();
        } catch (\Throwable) {
            return '#';
        }
    }

    private function safeCount(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ─── header quick actions ────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label('تحديث')->icon('heroicon-o-arrow-path')->color('gray')->action('refreshOps'),

            Action::make('revalidate_frontend')->label('تحديث الموقع الآن')->icon('heroicon-o-cloud')->color('success')
                ->action(function () {
                    $token = config('app.revalidate_token');
                    $url   = config('app.frontend_url') . '/api/revalidate';
                    if (! $token) {
                        Notification::make()->title('REVALIDATE_TOKEN غير مضبوط')->warning()->send();
                        return;
                    }
                    try {
                        $res = Http::timeout(10)->withHeaders(['x-revalidate-token' => $token])->post($url);
                        $res->successful()
                            ? Notification::make()->title('✓ تم تحديث الموقع بالكامل')->success()->send()
                            : Notification::make()->title('فشل — كود: ' . $res->status())->danger()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('خطأ: ' . $e->getMessage())->danger()->send();
                    }
                }),

            Action::make('clear_cache')->label('مسح الكاش')->icon('heroicon-o-trash')->color('warning')
                ->action(function () {
                    try {
                        Artisan::call('cache:clear');
                        Notification::make()->title('✓ تم مسح الكاش')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('خطأ: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
