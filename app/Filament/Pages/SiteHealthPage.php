<?php

namespace App\Filament\Pages;

use App\Models\Brand;
use App\Models\ContentItem;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SiteHealthPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'الإعدادات والمظهر';
    protected static ?string $title           = 'فحص الموقع';
    protected static ?int    $navigationSort   = 20;
    protected static string  $view            = 'filament.pages.site-health';

    public array $checks = [];
    public ?int  $brokenImages = null; // null = not scanned yet

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $this->runChecks();
    }

    public function runChecks(): void
    {
        $this->checks = [
            'database'      => $this->checkDatabase(),
            'r2'            => $this->checkR2(),
            'cors'          => $this->checkCors(),
            'cache'         => $this->checkCache(),
            'compiled'      => $this->checkCompiled(),
            'frontend'      => $this->checkFrontend(),
            'broken_images' => $this->checkBrokenImages(),
            'counts'        => $this->checkCounts(),
            'queue'         => $this->checkQueue(),
            'content'       => $this->checkContent(),
            'brands'        => $this->checkBrands(),
            'environment'   => $this->checkEnvironment(),
        ];
    }

    // ─── Checks ──────────────────────────────────────────────────────────────

    private function checkDatabase(): array
    {
        try {
            $t = microtime(true);
            DB::select('select 1');
            $ms = round((microtime(true) - $t) * 1000);
            return $this->ok("قاعدة البيانات", "متصلة — زمن الاستجابة {$ms}ms", 'runMigrations', 'تشغيل التحديثات', 'gray');
        } catch (\Throwable $e) {
            return $this->err("قاعدة البيانات", 'فشل الاتصال: ' . $e->getMessage(), 'runChecks', 'إعادة الفحص');
        }
    }

    private function checkR2(): array
    {
        $disk   = config('filesystems.default', 'public');
        $bucket = config('filesystems.disks.r2.bucket');
        if (!config('filesystems.disks.r2.key') || !$bucket) {
            return $this->warn("تخزين R2", 'R2 غير مفعّل — يستخدم التخزين المحلي', 'runChecks', 'إعادة الفحص');
        }
        try {
            Storage::disk($disk)->put('_health.txt', 'ok');
            $ok = Storage::disk($disk)->exists('_health.txt');
            Storage::disk($disk)->delete('_health.txt');
            return $ok
                ? $this->ok("تخزين R2", "يعمل — bucket: {$bucket}", 'testUpload', 'اختبار رفع')
                : $this->err("تخزين R2", 'لا يمكن الكتابة', 'testUpload', 'اختبار رفع');
        } catch (\Throwable $e) {
            return $this->err("تخزين R2", 'خطأ: ' . $e->getMessage(), 'testUpload', 'اختبار رفع');
        }
    }

    private function checkCors(): array
    {
        $publicUrl = rtrim((string) config('filesystems.disks.r2.url'), '/');
        if (!$publicUrl) {
            return $this->warn("CORS", 'لا يوجد رابط R2 عام مضبوط', 'applyCors', 'تطبيق CORS');
        }
        $disk = config('filesystems.default', 'public');
        $name = '_cors_probe.txt';
        try {
            Storage::disk($disk)->put($name, 'ok');
            $res = Http::timeout(8)->withHeaders([
                'Origin' => config('app.url', 'https://api.qev.app'),
            ])->get($publicUrl . '/' . $name);
            $allow = $res->header('Access-Control-Allow-Origin');
            Storage::disk($disk)->delete($name);
            return $allow
                ? $this->ok("CORS", "مضبوط — يسمح بـ {$allow}", 'applyCors', 'إعادة التطبيق', 'gray')
                : $this->warn("CORS", 'غير مضبوط — معاينة الصور في اللوحة قد تتعلّق', 'applyCors', 'تطبيق CORS', 'warning');
        } catch (\Throwable $e) {
            return $this->warn("CORS", 'تعذّر الفحص: ' . $e->getMessage(), 'applyCors', 'تطبيق CORS');
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::put('_health', '1', 5);
            $ok = Cache::get('_health') === '1';
            $driver = config('cache.default');
            return $ok
                ? $this->ok("الكاش", "يعمل — driver: {$driver}", 'clearCache', 'مسح الكاش')
                : $this->err("الكاش", 'لا يعمل', 'clearCache', 'مسح الكاش');
        } catch (\Throwable $e) {
            return $this->err("الكاش", 'خطأ: ' . $e->getMessage(), 'clearCache', 'مسح الكاش');
        }
    }

    private function checkCompiled(): array
    {
        $configCached = file_exists(base_path('bootstrap/cache/config.php'));
        $routeCached  = file_exists(base_path('bootstrap/cache/routes-v7.php'));
        $msg = 'config: ' . ($configCached ? 'مُخزّن' : 'غير مُخزّن') . ' • route: ' . ($routeCached ? 'مُخزّن' : 'غير مُخزّن');
        return $this->ok("الكاش المترجم", $msg, 'optimizeClear', 'مسح الكاش المترجم', 'gray');
    }

    private function checkFrontend(): array
    {
        $url = config('app.frontend_url', 'https://qev.app');
        try {
            $res  = Http::timeout(8)->withoutRedirecting()->get($url);
            $code = $res->status();
            return $code < 500
                ? $this->ok("الموقع الأمامي", "يستجيب ({$code})", 'revalidateFrontend', 'تحديث الموقع', 'success')
                : $this->err("الموقع الأمامي", "أعاد خطأ {$code}", 'revalidateFrontend', 'تحديث الموقع');
        } catch (\Throwable $e) {
            return $this->err("الموقع الأمامي", 'لا يستجيب: ' . $e->getMessage(), 'revalidateFrontend', 'تحديث الموقع');
        }
    }

    private function checkBrokenImages(): array
    {
        if ($this->brokenImages === null) {
            return $this->warn("الصور المكسورة", 'لم يُفحص بعد — اضغط "فحص الصور"', 'scanBrokenImages', 'فحص الصور', 'warning');
        }
        return $this->brokenImages === 0
            ? $this->ok("الصور المكسورة", 'كل الصور سليمة ✓', 'scanBrokenImages', 'إعادة الفحص', 'gray')
            : $this->err("الصور المكسورة", "{$this->brokenImages} عنصر صورته مفقودة على R2", 'scanBrokenImages', 'إعادة الفحص');
    }

    private function checkCounts(): array
    {
        $brands = Brand::count();
        return $this->ok("عدّادات الماركات", "{$brands} ماركة — قد تحتاج إعادة حساب بعد إضافة محتوى", 'recomputeCounts', 'إعادة الحساب');
    }

    private function checkQueue(): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return $this->ok("الطوابير", 'لا يوجد نظام طوابير مفعّل', 'restartQueue', 'إعادة تشغيل العمّال', 'gray');
        }
        $failed = DB::table('failed_jobs')->count();
        return $failed === 0
            ? $this->ok("الطوابير", 'لا توجد مهام فاشلة', 'restartQueue', 'إعادة تشغيل العمّال', 'gray')
            : $this->warn("الطوابير", "{$failed} مهمة فاشلة", 'retryFailedJobs', 'إعادة محاولة الفاشلة', 'warning');
    }

    private function checkContent(): array
    {
        $published = ContentItem::where('status', 'published')->count();
        $draft     = ContentItem::where('status', 'draft')->count();
        return $this->ok("المحتوى", "منشور: {$published} — مسودة: {$draft}", 'runChecks', 'إعادة الفحص', 'gray');
    }

    private function checkBrands(): array
    {
        $active   = Brand::where('is_active', true)->count();
        $hidden   = Brand::where('maintenance_mode', true)->count();
        $msg = "فعّالة: {$active}" . ($hidden ? " — في الصيانة: {$hidden}" : '');
        return $this->ok("الماركات", $msg, 'runChecks', 'إعادة الفحص', 'gray');
    }

    private function checkEnvironment(): array
    {
        $env   = app()->environment();
        $debug = config('app.debug') ? 'مفعّل ⚠' : 'مغلق';
        $https = str_starts_with((string) config('app.url'), 'https') ? 'HTTPS ✓' : 'HTTP ⚠';
        $status = (config('app.debug') && $env === 'production') ? 'warning' : 'ok';
        $msg = "البيئة: {$env} • Debug: {$debug} • {$https}";
        return $status === 'warning'
            ? $this->warn("البيئة", $msg . ' — يُفضّل إغلاق Debug في الإنتاج', 'runChecks', 'إعادة الفحص')
            : $this->ok("البيئة", $msg, 'runChecks', 'إعادة الفحص', 'gray');
    }

    // ─── Actions ───────────────────────────────────────────────────────────────

    public function runMigrations(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->notify('✓ تم تشغيل التحديثات', 'success');
        } catch (\Throwable $e) {
            $this->notify('فشل: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function testUpload(): void
    {
        $disk = config('filesystems.default', 'public');
        try {
            $name = '_health_' . uniqid() . '.txt';
            Storage::disk($disk)->put($name, 'health-check');
            $exists = Storage::disk($disk)->exists($name);
            Storage::disk($disk)->delete($name);
            $this->notify($exists ? '✓ الرفع والحذف يعملان' : 'فشل الرفع', $exists ? 'success' : 'danger');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function applyCors(): void
    {
        $bucket = config('filesystems.disks.r2.bucket');
        try {
            $client = Storage::disk('r2')->getClient();
            $client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => ['CORSRules' => [[
                    'AllowedOrigins' => ['https://api.qev.app', 'https://qev.app', 'https://www.qev.app'],
                    'AllowedMethods' => ['GET', 'PUT', 'POST', 'HEAD', 'DELETE'],
                    'AllowedHeaders' => ['*'],
                    'ExposeHeaders'  => ['ETag'],
                    'MaxAgeSeconds'  => 3600,
                ]]],
            ]);
            $this->notify('✓ تم تطبيق CORS على R2', 'success');
        } catch (\Throwable $e) {
            $this->notify('فشل تطبيق CORS (المفتاح قد لا يملك الصلاحية): ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function clearCache(): void
    {
        try {
            Artisan::call('cache:clear');
            $this->notify('✓ تم مسح الكاش', 'success');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function optimizeClear(): void
    {
        try {
            Artisan::call('optimize:clear');
            $this->notify('✓ تم مسح الكاش المترجم (config/route/view)', 'success');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function scanBrokenImages(): void
    {
        $disk = config('filesystems.default', 'public');
        $broken = 0;
        try {
            ContentItem::where('status', 'published')
                ->whereNotNull('image_path')
                ->orderByDesc('id')
                ->limit(300)
                ->pluck('image_path')
                ->each(function ($path) use ($disk, &$broken) {
                    try {
                        if (!Storage::disk($disk)->exists($path)) $broken++;
                    } catch (\Throwable) {
                        $broken++;
                    }
                });
            $this->brokenImages = $broken;
            $this->notify($broken === 0 ? '✓ كل الصور سليمة' : "⚠ {$broken} صورة مفقودة", $broken === 0 ? 'success' : 'warning');
        } catch (\Throwable $e) {
            $this->notify('خطأ في الفحص: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function recomputeCounts(): void
    {
        try {
            Brand::all()->each(fn (Brand $b) => $b->refreshCounts());
            $this->notify('✓ تم إعادة حساب عدّادات كل الماركات', 'success');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function restartQueue(): void
    {
        try {
            Artisan::call('queue:restart');
            $this->notify('✓ تم إرسال إشارة إعادة تشغيل للعمّال', 'success');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function retryFailedJobs(): void
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
            $this->notify('✓ تم جدولة إعادة المحاولة للمهام الفاشلة', 'success');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function revalidateFrontend(): void
    {
        $token = config('app.revalidate_token');
        $url   = config('app.frontend_url') . '/api/revalidate';
        if (!$token) {
            $this->notify('REVALIDATE_TOKEN غير مضبوط', 'warning');
            return;
        }
        try {
            $res = Http::timeout(10)->withHeaders(['x-revalidate-token' => $token])->post($url);
            $res->successful()
                ? $this->notify('✓ تم تحديث الموقع', 'success')
                : $this->notify('فشل التحديث — كود: ' . $res->status(), 'danger');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function ok(string $label, string $msg, ?string $action = null, ?string $actionLabel = null, string $actionColor = 'primary'): array
    {
        return compact('label', 'msg', 'action', 'actionLabel', 'actionColor') + ['status' => 'ok', 'message' => $msg];
    }

    private function warn(string $label, string $msg, ?string $action = null, ?string $actionLabel = null, string $actionColor = 'warning'): array
    {
        return compact('label', 'msg', 'action', 'actionLabel', 'actionColor') + ['status' => 'warning', 'message' => $msg];
    }

    private function err(string $label, string $msg, ?string $action = null, ?string $actionLabel = null, string $actionColor = 'danger'): array
    {
        return compact('label', 'msg', 'action', 'actionLabel', 'actionColor') + ['status' => 'error', 'message' => $msg];
    }

    private function notify(string $title, string $type): void
    {
        Notification::make()->title($title)->{$type}()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revalidate')->label('تحديث الموقع الآن')
                ->icon('heroicon-o-arrow-path')->color('success')->action('revalidateFrontend'),
            Action::make('generate_thumbnails')->label('توليد مصغّرات الصور')
                ->icon('heroicon-o-photo')->color('warning')
                ->requiresConfirmation()
                ->modalHeading('توليد مصغّرات الصور')
                ->modalDescription('يولّد نسخًا مصغّرة خفيفة للصور القديمة لتسريع لوحة التحكم والموقع. قد ياخذ بضع ثوانٍ.')
                ->action('generateThumbnails'),
            Action::make('refresh_checks')->label('إعادة الفحص')
                ->icon('heroicon-o-magnifying-glass')->color('gray')->action('runChecks'),
        ];
    }

    public function generateThumbnails(): void
    {
        $svc  = app(\App\Services\ImageThumbnailService::class);
        $done = 0;

        ContentItem::whereNotNull('image_path')
            ->where(fn ($q) => $q
                ->whereNull('thumbnail_path')
                ->orWhere('thumbnail_path', 'not like', '%content-items/thumbs%'))
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->each(function (ContentItem $item) use ($svc, &$done) {
                if ($svc->refreshFor($item)) {
                    $done++;
                }
            });

        Notification::make()
            ->title($done > 0 ? "تم توليد {$done} صورة مصغّرة" : 'كل الصور لها مصغّرات بالفعل')
            ->success()
            ->send();
    }
}
