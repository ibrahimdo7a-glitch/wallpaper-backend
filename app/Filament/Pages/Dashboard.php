<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestWallpapersWidget;
use App\Filament\Widgets\PendingReviewWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopModeratorsWidget;
use App\Filament\Widgets\TopWallpapersWidget;
use App\Filament\Widgets\DownloadsChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'لوحة التحكم';

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            DownloadsChartWidget::class,
            PendingReviewWidget::class,
            TopWallpapersWidget::class,
            TopModeratorsWidget::class,
            LatestWallpapersWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 2;
    }
}
