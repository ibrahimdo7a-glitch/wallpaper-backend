<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WallpaperResource;
use App\Filament\Resources\WatermarkResource;
use App\Filament\Widgets\DownloadsChartWidget;
use App\Filament\Widgets\LatestWallpapersWidget;
use App\Filament\Widgets\PendingReviewWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopModeratorsWidget;
use App\Filament\Widgets\TopWallpapersWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Blue,
                'danger' => Color::Red,
                'info' => Color::Cyan,
                'success' => Color::Green,
                'warning' => Color::Orange,
            ])
            ->font('Noto Kufi Arabic')
            ->brandName('QEV - لوحة التحكم')
            ->favicon(asset('favicon.ico'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->navigationGroups([
                NavigationGroup::make('التحليلات')->icon('heroicon-o-chart-bar'),
                NavigationGroup::make('السيارات والماركات')->icon('heroicon-o-truck'),
                NavigationGroup::make('المحتوى')->icon('heroicon-o-squares-2x2'),
                NavigationGroup::make('الأخبار')->icon('heroicon-o-newspaper'),
                NavigationGroup::make('التطبيقات')->icon('heroicon-o-device-phone-mobile'),
                NavigationGroup::make('سوق السيارات')->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make('سوق القطع والاكسسوارات')->icon('heroicon-o-wrench'),
                NavigationGroup::make('الأعضاء')->icon('heroicon-o-user-group'),
                NavigationGroup::make('الإدارة')->icon('heroicon-o-users'),
                NavigationGroup::make('الإعدادات والمظهر')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => view('filament.image-zoom')->render(),
            );
    }
}
