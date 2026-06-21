<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SiteHealthPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $title = 'فحص الموقع';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.site-health';

    public array $checks = [];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $this->runChecks();
    }

    public function runChecks(): void
    {
        $this->checks = [
            'database'   => $this->checkDatabase(),
            'storage'    => $this->checkStorage(),
            'r2'         => $this->checkR2(),
            'frontend'   => $this->checkFrontend(),
            'api'        => $this->checkApi(),
            'wallpapers' => $this->checkWallpapers(),
            'categories' => $this->checkCategories(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $count = DB::table('wallpapers')->count();
            return ['status' => 'ok', 'message' => "متصل — {$count} خلفية في قاعدة البيانات"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'فشل الاتصال: ' . $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        $disk = config('filesystems.default', 'public');
        try {
            Storage::disk($disk)->put('_health_check.txt', 'ok');
            $exists = Storage::disk($disk)->exists('_health_check.txt');
            Storage::disk($disk)->delete('_health_check.txt');
            return $exists
                ? ['status' => 'ok', 'message' => "التخزين ({$disk}) يعمل"]
                : ['status' => 'error', 'message' => "التخزين ({$disk}) لا يمكن الكتابة فيه"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => "خطأ في التخزين ({$disk}): " . $e->getMessage()];
        }
    }

    private function checkR2(): array
    {
        $key = config('filesystems.disks.r2.key');
        $bucket = config('filesystems.disks.r2.bucket');
        if (!$key || !$bucket) {
            return ['status' => 'warning', 'message' => 'R2 غير مفعّل — يستخدم التخزين المحلي'];
        }
        return ['status' => 'ok', 'message' => "R2 مفعّل — bucket: {$bucket}"];
    }

    private function checkFrontend(): array
    {
        $url = config('app.frontend_url', 'https://qev.app');
        try {
            $res = Http::timeout(5)->get($url);
            return $res->successful()
                ? ['status' => 'ok', 'message' => "الموقع يستجيب ({$res->status()})"]
                : ['status' => 'error', 'message' => "الموقع أعاد خطأ {$res->status()}"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'الموقع لا يستجيب: ' . $e->getMessage()];
        }
    }

    private function checkApi(): array
    {
        try {
            // Check internally via DB (avoids self-HTTP call timeout in Railway)
            $published = \App\Models\Wallpaper::where('status', 'published')->count();
            $routes    = \Illuminate\Support\Facades\Route::getRoutes()->count();
            return ['status' => 'ok', 'message' => "API جاهز — {$routes} route مسجّل — {$published} خلفية منشورة"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'خطأ: ' . $e->getMessage()];
        }
    }

    private function checkWallpapers(): array
    {
        $total     = DB::table('wallpapers')->whereNull('deleted_at')->count();
        $published = DB::table('wallpapers')->whereNull('deleted_at')->where('status', 'published')->count();
        $noThumb   = DB::table('wallpapers')->whereNull('deleted_at')->where('status', 'published')->whereNull('thumbnail_file')->count();
        $msg = "المجموع: {$total} — منشور: {$published}";
        if ($noThumb > 0) {
            return ['status' => 'warning', 'message' => $msg . " — {$noThumb} بدون thumbnail"];
        }
        return ['status' => 'ok', 'message' => $msg];
    }

    private function checkCategories(): array
    {
        $total  = DB::table('categories')->whereNull('deleted_at')->count();
        $active = DB::table('categories')->whereNull('deleted_at')->where('is_active', true)->count();
        return ['status' => 'ok', 'message' => "المجموع: {$total} — فعّال: {$active}"];
    }

    public function revalidateFrontend(): void
    {
        $token = config('app.revalidate_token');
        $url   = config('app.frontend_url') . '/api/revalidate';

        if (!$token) {
            Notification::make()->title('REVALIDATE_TOKEN غير مضبوط')->warning()->send();
            return;
        }

        try {
            $res = Http::timeout(10)->withHeaders(['x-revalidate-token' => $token])->post($url);
            if ($res->successful()) {
                Cache::forget('categories.tree');
                Notification::make()->title('✓ تم تحديث الموقع بالكامل')->success()->send();
            } else {
                Notification::make()->title('فشل التحديث — كود: ' . $res->status())->danger()->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->title('خطأ: ' . $e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revalidate')
                ->label('تحديث الموقع الآن')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action('revalidateFrontend'),

            Action::make('refresh_checks')
                ->label('إعادة الفحص')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action('runChecks'),
        ];
    }
}
