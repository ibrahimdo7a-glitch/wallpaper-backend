<?php

namespace App\Filament\Pages;

use App\Services\StatisticsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class StatisticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'التحليلات';

    protected static ?string $title = 'الإحصائيات';

    protected static ?string $navigationLabel = 'الإحصائيات';

    protected static ?int $navigationSort = -2;

    protected static string $view = 'filament.pages.statistics';

    public string $period = '30d';

    public array $overview     = [];
    public array $comparisons  = [];
    public array $chartData    = [];
    public array $topContent   = [];
    public array $uploaders    = [];
    public array $moderation   = [];
    public array $health       = [];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $this->loadAll();
    }

    public function loadAll(): void
    {
        $svc = app(StatisticsService::class);

        $this->overview    = $svc->getOverview($this->period);
        $this->comparisons = $svc->getComparisons();
        $this->chartData   = $svc->getChartData($this->period);
        $this->topContent  = $svc->getTopContent();
        $this->uploaders   = $svc->getTopUploaders();
        $this->moderation  = $svc->getModeration();
        $this->health      = $svc->getHealth();
    }

    public function setPeriod(string $period): void
    {
        $this->period    = $period;
        $this->overview  = app(StatisticsService::class)->getOverview($period);
        $this->chartData = app(StatisticsService::class)->getChartData($period);
        $this->dispatch('statsChartsInit', chartData: $this->chartData);
    }

    public function refresh(): void
    {
        app(StatisticsService::class)->flushAll();
        $this->loadAll();
        $this->dispatch('statsChartsInit', chartData: $this->chartData);
        Notification::make()->title('تم تحديث جميع البيانات')->success()->send();
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rows     = app(StatisticsService::class)->buildCsvRows($this->period);
        $filename = 'qev-stats-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $h = fopen('php://output', 'w');
            // UTF-8 BOM for Arabic in Excel
            fwrite($h, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($h, (array) $row);
            }
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('تصدير CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action('exportCsv'),

            Action::make('refresh')
                ->label('تحديث')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action('refresh'),
        ];
    }
}
