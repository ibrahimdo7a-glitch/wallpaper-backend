<?php

namespace App\Filament\Pages;

use App\Services\AnalyticsService;
use Filament\Actions\Action;
use Filament\Pages\Page;

class LiveVisitorsPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'التحليلات';
    protected static ?string $title           = 'الزوّار المباشرون';
    protected static ?int    $navigationSort   = 2;
    protected static string  $view            = 'filament.pages.live-visitors';

    public array $visitors = [];
    public int $count = 0;
    public bool $showIp = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $svc = app(AnalyticsService::class);
        $this->visitors = $svc->liveVisitors(AnalyticsService::ONLINE_MINUTES, 200);
        $this->count = count($this->visitors);
    }

    public function maskIp(?string $ip): string
    {
        if (! $ip) {
            return '—';
        }
        if ($this->showIp) {
            return $ip;
        }
        if (str_contains($ip, '.')) { // IPv4 → hide last octet
            return preg_replace('/\.\d+$/', '.•••', $ip);
        }
        return substr($ip, 0, 8) . '…'; // IPv6 → prefix only
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleIp')
                ->label(fn () => $this->showIp ? 'إخفاء IP' : 'إظهار IP')
                ->icon('heroicon-o-eye')->color('gray')
                ->action(fn () => $this->showIp = ! $this->showIp),
            Action::make('refresh')->label('تحديث الآن')->icon('heroicon-o-arrow-path')->color('primary')->action('refresh'),
        ];
    }
}
