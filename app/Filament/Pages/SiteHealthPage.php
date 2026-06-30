<?php

namespace App\Filament\Pages;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Services\HealthCheckService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SiteHealthPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'الإعدادات والمظهر';
    protected static ?string $title           = 'فحص الموقع';
    protected static ?int    $navigationSort   = 20;
    protected static string  $view            = 'filament.pages.site-health';

    /** Full structured report from HealthCheckService. */
    public array $report = [];

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
        $this->report = app(HealthCheckService::class)->run();
    }

    // ─── Header actions ────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label('إعادة الفحص')
                ->icon('heroicon-o-arrow-path')->color('primary')->action('runChecks'),

            Action::make('download')->label('تحميل التقرير (.txt)')
                ->icon('heroicon-o-arrow-down-tray')->color('gray')->action('downloadReport'),

            ActionGroup::make([
                Action::make('clearCache')->label('مسح الكاش')->icon('heroicon-o-trash')->action('clearCache'),
                Action::make('optimizeClear')->label('مسح الكاش المترجم')->icon('heroicon-o-bolt')->action('optimizeClear'),
                Action::make('runMigrations')->label('تشغيل التحديثات (migrate)')->icon('heroicon-o-circle-stack')
                    ->requiresConfirmation()->action('runMigrations'),
                Action::make('createStorageLink')->label('إنشاء storage link')->icon('heroicon-o-link')->action('createStorageLink'),
                Action::make('retryFailedJobs')->label('إعادة المهام الفاشلة')->icon('heroicon-o-arrow-uturn-left')->action('retryFailedJobs'),
                Action::make('applyCors')->label('تطبيق CORS على R2')->icon('heroicon-o-globe-alt')->action('applyCors'),
                Action::make('testUpload')->label('اختبار رفع R2')->icon('heroicon-o-cloud-arrow-up')->action('testUpload'),
                Action::make('recomputeCounts')->label('إعادة حساب العدّادات')->icon('heroicon-o-calculator')->action('recomputeCounts'),
                Action::make('scanBrokenImages')->label('فحص الصور المكسورة')->icon('heroicon-o-photo')->action('scanBrokenImages'),
                Action::make('generateThumbnails')->label('توليد المصغّرات')->icon('heroicon-o-square-2-stack')->action('generateThumbnails'),
            ])->label('أدوات الإصلاح')->icon('heroicon-o-wrench')->button()->color('warning'),

            Action::make('revalidate')->label('تحديث الموقع الآن')
                ->icon('heroicon-o-cloud')->color('success')->action('revalidateFrontend'),
        ];
    }

    // ─── Remediations (with before/after where meaningful) ───────────────────

    public function clearCache(): void
    {
        $this->safe(fn () => Artisan::call('cache:clear'), '✓ تم مسح الكاش');
    }

    public function optimizeClear(): void
    {
        $this->safe(fn () => Artisan::call('optimize:clear'), '✓ تم مسح الكاش المترجم (config/route/view)');
    }

    public function runMigrations(): void
    {
        $before = $this->pendingMigrations();
        try {
            Artisan::call('migrate', ['--force' => true]);
            $after = $this->pendingMigrations();
            $this->notify("✓ التحديثات — المعلّقة: {$before} → {$after}", 'success');
        } catch (\Throwable $e) {
            $this->notify('فشل: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    public function createStorageLink(): void
    {
        $before = $this->storageLinkState();
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            // link may already exist — fall through to report the state
        }
        $after = $this->storageLinkState();
        $this->notify("storage link: {$before} → {$after}", $after === 'موجود' ? 'success' : 'warning');
        $this->runChecks();
    }

    public function retryFailedJobs(): void
    {
        $before = $this->failedJobs();
        if ($before === 0) {
            $this->notify('لا توجد مهام فاشلة لإعادتها', 'success');
            return;
        }
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
            $after = $this->failedJobs();
            $this->notify("المهام الفاشلة: {$before} → {$after}", 'success');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
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

    public function scanBrokenImages(): void
    {
        $disk = config('filesystems.default', 'public');
        $broken = 0;
        try {
            ContentItem::where('status', 'published')->whereNotNull('image_path')
                ->orderByDesc('id')->limit(300)->pluck('image_path')
                ->each(function ($path) use ($disk, &$broken) {
                    try {
                        if (! Storage::disk($disk)->exists($path)) $broken++;
                    } catch (\Throwable) {
                        $broken++;
                    }
                });
            $this->notify($broken === 0 ? '✓ كل الصور سليمة' : "⚠ {$broken} صورة مفقودة على R2", $broken === 0 ? 'success' : 'warning');
        } catch (\Throwable $e) {
            $this->notify('خطأ في الفحص: ' . $e->getMessage(), 'danger');
        }
    }

    public function generateThumbnails(): void
    {
        $svc  = app(\App\Services\ImageThumbnailService::class);
        $done = 0;
        ContentItem::whereNotNull('image_path')
            ->where(fn ($q) => $q->whereNull('thumbnail_path')->orWhere('thumbnail_path', 'not like', '%content-items/thumbs%'))
            ->orderBy('id')->limit(1000)->get()
            ->each(function (ContentItem $item) use ($svc, &$done) {
                if ($svc->refreshFor($item)) $done++;
            });
        $this->notify($done > 0 ? "تم توليد {$done} صورة مصغّرة" : 'كل الصور لها مصغّرات بالفعل', 'success');
    }

    public function revalidateFrontend(): void
    {
        $token = config('app.revalidate_token');
        $url   = config('app.frontend_url') . '/api/revalidate';
        if (! $token) {
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

    public function downloadReport()
    {
        $report = $this->report ?: app(HealthCheckService::class)->run();
        $text = $this->reportToText($report);
        $name = 'health-report-' . now()->format('Y-m-d-H-i') . '.txt';

        return response()->streamDownload(function () use ($text) {
            echo $text;
        }, $name, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function safe(callable $fn, string $okMsg): void
    {
        try {
            $fn();
            $this->notify($okMsg, 'success');
        } catch (\Throwable $e) {
            $this->notify('خطأ: ' . $e->getMessage(), 'danger');
        }
        $this->runChecks();
    }

    private function pendingMigrations(): int
    {
        try {
            $ran = DB::table('migrations')->count();
            $files = count(glob(database_path('migrations/*.php')) ?: []);
            return max(0, $files - $ran);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function failedJobs(): int
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function storageLinkState(): string
    {
        return (is_link(public_path('storage')) || file_exists(public_path('storage'))) ? 'موجود' : 'مفقود';
    }

    private function notify(string $title, string $type): void
    {
        Notification::make()->title($title)->{$type}()->send();
    }

    private function reportToText(array $report): string
    {
        $icon = ['ok' => '[OK]', 'warn' => '[!]', 'error' => '[X]', 'na' => '[-]'];
        $s = $report['summary'] ?? ['ok' => 0, 'warn' => 0, 'error' => 0, 'na' => 0];

        $lines = [];
        $lines[] = 'QEV — تقرير فحص الموقع والخدمات';
        $lines[] = str_repeat('=', 50);
        $lines[] = "التاريخ: {$report['generated_at']}    مدة الفحص: {$report['duration_ms']}ms";
        $lines[] = "ناجح: {$s['ok']}   تحذير: {$s['warn']}   فشل: {$s['error']}   غير مطبّق: {$s['na']}";
        $lines[] = str_repeat('=', 50);

        foreach ($report['sections'] ?? [] as $section) {
            $lines[] = '';
            $lines[] = $section['title'];
            foreach ($section['checks'] as $c) {
                $tag = $icon[$c['status']] ?? '[?]';
                $note = $c['note'] ? "  — {$c['note']}" : '';
                $lines[] = "  {$tag} {$c['label']}: {$c['value']}{$note}";
            }
        }

        $r = $report['resources'] ?? [];
        $lines[] = '';
        $lines[] = 'الموارد:';
        if (! empty($r['ram'])) $lines[] = "  RAM: {$r['ram']['used_pct']}%";
        if (! empty($r['disk'])) $lines[] = "  Disk: {$r['disk']['used_pct']}%";
        if (! empty($r['uptime'])) $lines[] = "  Uptime: {$r['uptime']}";

        if (! empty($report['recommendations'])) {
            $lines[] = '';
            $lines[] = 'توصيات الإصلاح:';
            foreach ($report['recommendations'] as $i => $rec) {
                $n = $i + 1;
                $lines[] = "  {$n}. {$rec}";
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
