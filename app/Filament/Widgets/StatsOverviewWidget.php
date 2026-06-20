<?php

namespace App\Filament\Widgets;

use App\Models\Download;
use App\Models\Like;
use App\Models\User;
use App\Models\Wallpaper;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $canViewGlobal = $user->hasPermissionTo('can_view_global_statistics');

        if ($canViewGlobal) {
            return $this->getGlobalStats();
        }

        return $this->getUserStats($user);
    }

    protected function getGlobalStats(): array
    {
        $totalWallpapers = Wallpaper::count();
        $published = Wallpaper::where('status', 'published')->count();
        $pending = Wallpaper::where('status', 'pending')->count();
        $totalDownloads = Download::count();
        $todayDownloads = Download::whereDate('created_at', today())->count();
        $totalLikes = Like::count();
        $totalModerators = User::whereHas('roles')->count();

        return [
            Stat::make('إجمالي الخلفيات', number_format($totalWallpapers))
                ->description("منشور: {$published} | انتظار: {$pending}")
                ->color('primary')
                ->icon('heroicon-o-photo'),

            Stat::make('إجمالي التحميلات', number_format($totalDownloads))
                ->description("اليوم: {$todayDownloads}")
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),

            Stat::make('إجمالي الإعجابات', number_format($totalLikes))
                ->color('warning')
                ->icon('heroicon-o-heart'),

            Stat::make('المشرفون', number_format($totalModerators))
                ->color('info')
                ->icon('heroicon-o-users'),

            Stat::make('قيد الانتظار', number_format($pending))
                ->color($pending > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock'),

            Stat::make('تحميلات الأسبوع', number_format(Download::where('created_at', '>=', now()->subDays(7))->count()))
                ->color('success')
                ->icon('heroicon-o-arrow-trending-up'),
        ];
    }

    protected function getUserStats($user): array
    {
        $wallpapers = $user->wallpapers();

        return [
            Stat::make('خلفياتي', number_format($wallpapers->count()))
                ->color('primary')
                ->icon('heroicon-o-photo'),

            Stat::make('المنشور', number_format($wallpapers->clone()->where('status', 'published')->count()))
                ->color('success'),

            Stat::make('قيد الانتظار', number_format($wallpapers->clone()->where('status', 'pending')->count()))
                ->color('warning'),

            Stat::make('تحميلاتي', number_format($wallpapers->clone()->sum('downloads_count')))
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray'),

            Stat::make('إعجاباتي', number_format($wallpapers->clone()->sum('likes_count')))
                ->color('danger')
                ->icon('heroicon-o-heart'),

            Stat::make('مشاهداتي', number_format($wallpapers->clone()->sum('views_count')))
                ->color('secondary')
                ->icon('heroicon-o-eye'),
        ];
    }
}
