<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestWallpapersWidget;
use App\Filament\Widgets\PendingReviewWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopModeratorsWidget;
use App\Filament\Widgets\TopWallpapersWidget;
use App\Filament\Widgets\DownloadsChartWidget;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Http;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revalidate_frontend')
                ->label('تحديث الموقع الآن')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action(function () {
                    $token = config('app.revalidate_token');
                    $url   = config('app.frontend_url') . '/api/revalidate';

                    if (!$token) {
                        Notification::make()->title('REVALIDATE_TOKEN غير مضبوط')->warning()->send();
                        return;
                    }

                    try {
                        $res = Http::timeout(10)->withHeaders(['x-revalidate-token' => $token])->post($url);
                        if ($res->successful()) {
                            \Illuminate\Support\Facades\Cache::forget('categories.tree');
                            Notification::make()->title('✓ تم تحديث الموقع بالكامل')->success()->send();
                        } else {
                            Notification::make()->title('فشل — كود: ' . $res->status())->danger()->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()->title('خطأ: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
