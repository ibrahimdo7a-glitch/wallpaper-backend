<?php

namespace App\Filament\Pages;

use App\Models\Member;
use App\Services\AnalyticsService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class AnalyticsPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationGroup = 'التحليلات';
    protected static ?string $title           = 'التحليلات';
    protected static ?int    $navigationSort   = 1;
    protected static string  $view            = 'filament.pages.analytics';

    public string $period = '7d';
    public array $report = [];

    /** @var array<string,string> */
    public array $periods = [
        'today'    => 'اليوم',
        '7d'       => 'آخر ٧ أيام',
        '30d'      => 'آخر ٣٠ يومًا',
        'month'    => 'هذا الشهر',
        'lifetime' => 'مدى الحياة',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $this->build();
    }

    public function setPeriod(string $p): void
    {
        $this->period = array_key_exists($p, $this->periods) ? $p : '7d';
        $this->build();
    }

    public function build(): void
    {
        $svc = app(AnalyticsService::class);
        [$from, $to, $prevFrom, $prevTo] = $this->range($this->period);

        // Period metrics + deltas vs the previous equal-length window.
        $visitors      = $svc->visitorsBetween($from, $to);
        $pageviews     = $svc->pageviewsBetween($from, $to);
        $newMembers    = $svc->newMembersBetween($from, $to);
        $hasPrev       = $this->period !== 'lifetime';
        $visitorsPrev  = $hasPrev ? $svc->visitorsBetween($prevFrom, $prevTo) : 0;
        $pageviewsPrev = $hasPrev ? $svc->pageviewsBetween($prevFrom, $prevTo) : 0;
        $membersPrev   = $hasPrev ? $svc->newMembersBetween($prevFrom, $prevTo) : 0;

        $online = $svc->online();

        $days   = $this->period === 'today' ? 1 : ($this->period === '30d' || $this->period === 'month' ? 30 : ($this->period === 'lifetime' ? 30 : 14));
        $days   = max(7, $days);
        $dailyV = $svc->dailyVisitors($days);
        $dailyP = $svc->dailyPageviews($days);

        $sources = $svc->trafficSourcesSince($this->period === 'lifetime' ? Carbon::createFromTimestamp(0) : $from);

        $this->report = [
            'period_label' => $this->periods[$this->period] ?? '',
            'has_prev'     => $hasPrev,
            'cards' => [
                ['label' => 'زوّار الفترة',       'value' => $visitors,  'delta' => $hasPrev ? $svc->delta($visitors, $visitorsPrev) : null,   'icon' => '🧑‍💻'],
                ['label' => 'مشاهدات الصفحات',    'value' => $pageviews, 'delta' => $hasPrev ? $svc->delta($pageviews, $pageviewsPrev) : null, 'icon' => '👁️'],
                ['label' => 'أعضاء جدد',          'value' => $newMembers,'delta' => $hasPrev ? $svc->delta($newMembers, $membersPrev) : null,  'icon' => '🆕'],
                ['label' => 'إجمالي الأعضاء',     'value' => $svc->totalMembers(),     'delta' => null, 'icon' => '👥'],
                ['label' => 'زوّار مدى الحياة',   'value' => $svc->lifetimeVisitors(), 'delta' => null, 'icon' => '🌍'],
                ['label' => 'مشاهدات مدى الحياة', 'value' => $svc->lifetimePageviews(),'delta' => null, 'icon' => '📄'],
            ],
            'online'  => $online,
            'sources' => $sources,
            'series'  => [
                'labels'    => array_map(fn ($d) => Carbon::parse($d)->format('m-d'), array_keys($dailyV)),
                'visitors'  => array_values($dailyV),
                'pageviews' => array_values($dailyP),
            ],
            'members' => [
                'today'   => $svc->newMembersSince(now()->startOfDay()),
                'week'    => $svc->newMembersSince(now()->subDays(7)),
                'month'   => $svc->newMembersSince(now()->subDays(30)),
                'active7' => $svc->activeMembersSince(now()->subDays(7)),
                'recent'  => Member::latest()->limit(8)->get(['name', 'telegram_username', 'created_at', 'last_login_at'])->toArray(),
            ],
        ];
    }

    /** @return array{0:Carbon,1:Carbon,2:Carbon,3:Carbon} [from, to, prevFrom, prevTo] */
    private function range(string $p): array
    {
        $now = now();
        return match ($p) {
            'today'    => [$now->copy()->startOfDay(), $now->copy(), $now->copy()->subDay()->startOfDay(), $now->copy()->startOfDay()],
            '30d'      => [$now->copy()->subDays(30), $now->copy(), $now->copy()->subDays(60), $now->copy()->subDays(30)],
            'month'    => [$now->copy()->startOfMonth(), $now->copy(), $now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->startOfMonth()],
            'lifetime' => [Carbon::createFromTimestamp(0), $now->copy(), Carbon::createFromTimestamp(0), Carbon::createFromTimestamp(0)],
            default    => [$now->copy()->subDays(7), $now->copy(), $now->copy()->subDays(14), $now->copy()->subDays(7)],
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label('تحديث')->icon('heroicon-o-arrow-path')->color('primary')->action('build'),
        ];
    }
}
