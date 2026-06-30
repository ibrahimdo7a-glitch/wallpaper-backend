<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Real diagnostics for the qev.app stack (Railway-managed Docker: php-fpm +
 * nginx, Postgres, R2; Vercel frontend). Every reading is measured live —
 * nothing is faked. Things that don't exist in this architecture (systemd,
 * service log files, Horizon) are reported honestly as "not available here"
 * rather than pretended. Returns a structured report consumed by both the
 * Filament page and the `health:check` artisan command.
 */
class HealthCheckService
{
    /** Status constants used across the report. */
    public const OK = 'ok';
    public const WARN = 'warn';
    public const ERROR = 'error';
    public const NA = 'na';

    private array $recommendations = [];

    public function run(): array
    {
        $this->recommendations = [];
        $started = microtime(true);

        // The self-probe doubles as the nginx/php-fpm liveness proof.
        $apiUp = $this->probe(rtrim((string) config('app.url', 'https://api.qev.app'), '/') . '/up');

        $sections = [
            'site'        => ['title' => '🌐 حالة الموقع', 'checks' => $this->checkSite($apiUp)],
            'services'    => ['title' => '⚙️ الخدمات', 'checks' => $this->checkServices($apiUp)],
            'database'    => ['title' => '🗄️ قاعدة البيانات', 'checks' => $this->checkDatabase()],
            'laravel'     => ['title' => '🧱 Laravel', 'checks' => $this->checkLaravel()],
            'storage'     => ['title' => '📁 التخزين والملفات', 'checks' => $this->checkStorage()],
            'r2'          => ['title' => '☁️ تخزين R2', 'checks' => $this->checkR2()],
            'performance' => ['title' => '📊 الأداء والموارد', 'checks' => $this->checkPerformance()],
            'errors'      => ['title' => '🧾 سجل الأخطاء', 'checks' => $this->checkErrors()],
        ];

        // Aggregate counts across every check.
        $summary = [self::OK => 0, self::WARN => 0, self::ERROR => 0, self::NA => 0];
        foreach ($sections as $section) {
            foreach ($section['checks'] as $c) {
                $summary[$c['status']] = ($summary[$c['status']] ?? 0) + 1;
            }
        }

        return [
            'generated_at'    => now()->format('Y-m-d H:i:s'),
            'duration_ms'     => round((microtime(true) - $started) * 1000),
            'summary'         => $summary,
            'sections'        => $sections,
            'resources'       => $this->resources(),
            'recommendations' => array_values(array_unique($this->recommendations)),
        ];
    }

    // ─────────────────────────────── helpers ───────────────────────────────

    private function check(string $label, string $status, string $value, string $note = ''): array
    {
        return compact('label', 'status', 'value', 'note');
    }

    private function recommend(string $text): void
    {
        $this->recommendations[] = $text;
    }

    /** Time an HTTP request; never throws. */
    private function probe(string $url): array
    {
        $t = microtime(true);
        try {
            $res = Http::timeout(8)->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'QEV-HealthCheck/1.0'])->get($url);
            $ms = (int) round((microtime(true) - $t) * 1000);
            $code = $res->status();
            $status = $code >= 200 && $code < 300 ? self::OK
                : ($code >= 300 && $code < 400 ? self::WARN
                : ($code >= 400 && $code < 500 ? self::WARN : self::ERROR));
            return ['url' => $url, 'code' => $code, 'ms' => $ms, 'status' => $status, 'error' => null];
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $t) * 1000);
            return ['url' => $url, 'code' => 0, 'ms' => $ms, 'status' => self::ERROR, 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────── 1) Site ───────────────────────────────

    private function checkSite(array $apiUp): array
    {
        $api   = rtrim((string) config('app.url', 'https://api.qev.app'), '/');
        $front = rtrim((string) config('app.frontend_url', 'https://qev.app'), '/');

        $targets = [
            ['الموقع الأمامي (الرئيسية)', $this->probe($front)],
            ['لوحة التحكم', $this->probe($api . '/admin/login')],
            ['صحة API', $apiUp],
            ['API السوق', $this->probe($api . '/api/v1/market?section=cars&per_page=1')],
            ['خريطة الموقع', $this->probe($front . '/sitemap.xml')],
            ['robots.txt', $this->probe($front . '/robots.txt')],
        ];

        $out = [];
        foreach ($targets as [$label, $p]) {
            if ($p['error']) {
                $out[] = $this->check($label, self::ERROR, 'لا يستجيب', $p['error']);
                $this->recommend("«{$label}» لا يستجيب — تحقّق من النشر والدومين.");
                continue;
            }
            $code = $p['code'];
            $valueCode = $code >= 300 && $code < 400 ? "{$code} (تحويل)" : (string) $code;
            $out[] = $this->check($label, $p['status'], "HTTP {$valueCode} — {$p['ms']}ms");
            if ($code >= 500) {
                $this->recommend("«{$label}» يرجع خطأ {$code} — راجع سجل الأخطاء.");
            }
            if ($p['ms'] > 2500) {
                $this->recommend("«{$label}» بطيء ({$p['ms']}ms) — راقب الأداء/الكاش.");
            }
        }

        return $out;
    }

    // ───────────────────────────── 2) Services ─────────────────────────────

    private function checkServices(array $apiUp): array
    {
        $out = [];

        // nginx + php-fpm liveness is proven by the /up probe (the only two
        // processes the Railway container runs).
        if ($apiUp['code'] >= 200 && $apiUp['code'] < 400) {
            $out[] = $this->check('nginx + PHP-FPM', self::OK, "يعملان — استجابة /up خلال {$apiUp['ms']}ms");
        } else {
            $out[] = $this->check('nginx + PHP-FPM', self::ERROR, 'الواجهة الخلفية لا تستجيب');
            $this->recommend('الـAPI لا يستجيب — أعد نشر خدمة Railway.');
        }

        // PostgreSQL
        try {
            $t = microtime(true);
            $ver = DB::selectOne('select version() as v');
            $ms = (int) round((microtime(true) - $t) * 1000);
            $short = preg_match('/PostgreSQL ([\d.]+)/', $ver->v ?? '', $m) ? "PostgreSQL {$m[1]}" : 'PostgreSQL';
            $out[] = $this->check('قاعدة البيانات (PostgreSQL)', self::OK, "{$short} — متصلة خلال {$ms}ms");
        } catch (\Throwable $e) {
            $out[] = $this->check('قاعدة البيانات (PostgreSQL)', self::ERROR, 'فشل الاتصال', $e->getMessage());
            $this->recommend('فشل الاتصال بقاعدة البيانات — تحقّق من متغيرات DB في Railway.');
        }

        // Redis — only if actually configured for cache/queue/session.
        $usesRedis = in_array('redis', [config('cache.default'), config('queue.default'), config('session.driver')], true);
        if ($usesRedis) {
            try {
                $t = microtime(true);
                app('redis')->connection()->ping();
                $ms = (int) round((microtime(true) - $t) * 1000);
                $out[] = $this->check('Redis', self::OK, "يستجيب خلال {$ms}ms");
            } catch (\Throwable $e) {
                $out[] = $this->check('Redis', self::ERROR, 'مُهيّأ لكن لا يستجيب', $e->getMessage());
                $this->recommend('Redis مُهيّأ لكنه لا يستجيب — أضف خدمة Redis أو بدّل الـdriver.');
            }
        } else {
            $out[] = $this->check('Redis', self::NA, 'غير مستخدم في هذه البنية');
        }

        // Queue worker
        $queue = (string) config('queue.default');
        if ($queue === 'sync') {
            $out[] = $this->check('الطوابير (Queue)', self::OK, 'تزامني (sync) — ينفّذ فورًا، لا يحتاج عامل');
        } else {
            $out[] = $this->check('الطوابير (Queue)', self::WARN, "connection={$queue} — لا يوجد عامل (worker) في الحاوية", 'المهام المؤجَّلة لن تُنفَّذ');
            $this->recommend("QUEUE_CONNECTION={$queue} لكن لا يوجد worker على Railway — حوّله إلى sync أو أضف خدمة worker.");
        }

        // Scheduler / cron
        try {
            $events = app(Schedule::class)->events();
            $count = count($events);
            if ($count === 0) {
                $out[] = $this->check('المجدول (Scheduler)', self::OK, 'لا توجد مهام مجدولة');
            } else {
                $out[] = $this->check('المجدول (Scheduler)', self::WARN, "{$count} مهمة مجدولة — لا يوجد cron على Railway", 'المهام المجدولة لن تعمل تلقائيًا');
                $this->recommend("توجد {$count} مهمة مجدولة بدون cron — أضف Railway Cron أو خدمة جدولة خارجية.");
            }
        } catch (\Throwable $e) {
            $out[] = $this->check('المجدول (Scheduler)', self::NA, 'تعذّر القراءة', $e->getMessage());
        }

        // Next.js (Vercel) — tied to the frontend probe.
        $front = $this->probe(rtrim((string) config('app.frontend_url', 'https://qev.app'), '/'));
        $out[] = $front['code'] > 0 && $front['code'] < 500
            ? $this->check('الواجهة Next.js (Vercel)', self::OK, "تعمل — HTTP {$front['code']}")
            : $this->check('الواجهة Next.js (Vercel)', self::ERROR, 'لا تستجيب', $front['error'] ?? '');

        // Horizon
        $out[] = class_exists(\Laravel\Horizon\Horizon::class)
            ? $this->check('Laravel Horizon', self::OK, 'مثبّت')
            : $this->check('Laravel Horizon', self::NA, 'غير مثبّت (لا يُستخدم)');

        return $out;
    }

    // ───────────────────────────── 7) Database ─────────────────────────────

    private function checkDatabase(): array
    {
        $out = [];
        try {
            $tables = DB::selectOne("select count(*) as c from pg_tables where schemaname = 'public'");
            $out[] = $this->check('عدد الجداول', self::OK, (string) ($tables->c ?? 0) . ' جدول');
        } catch (\Throwable $e) {
            $out[] = $this->check('عدد الجداول', self::ERROR, 'تعذّر', $e->getMessage());
        }

        // Migrations: ran vs files on disk → pending.
        try {
            $ran = DB::table('migrations')->count();
            $files = count(glob(database_path('migrations/*.php')) ?: []);
            $pending = max(0, $files - $ran);
            $last = DB::table('migrations')->orderByDesc('id')->value('migration');
            $out[] = $this->check('آخر migration', self::OK, $last ? (string) $last : '—');
            $out[] = $pending === 0
                ? $this->check('migrations معلّقة', self::OK, "لا يوجد ({$ran} منفّذة)")
                : $this->check('migrations معلّقة', self::WARN, "{$pending} معلّقة", 'شغّل php artisan migrate');
            if ($pending > 0) {
                $this->recommend("يوجد {$pending} migration معلّق — شغّل «تشغيل التحديثات» أو php artisan migrate --force.");
            }
        } catch (\Throwable $e) {
            $out[] = $this->check('migrations', self::ERROR, 'تعذّر', $e->getMessage());
        }

        try {
            $size = DB::selectOne('select pg_size_pretty(pg_database_size(current_database())) as s');
            $out[] = $this->check('حجم قاعدة البيانات', self::OK, (string) ($size->s ?? '—'));
        } catch (\Throwable $e) {
            $out[] = $this->check('حجم قاعدة البيانات', self::NA, 'تعذّر القياس');
        }

        try {
            $conns = DB::selectOne('select count(*) as c from pg_stat_activity where datname = current_database()');
            $out[] = $this->check('الاتصالات الحالية', self::OK, (string) ($conns->c ?? 0) . ' اتصال');
        } catch (\Throwable $e) {
            $out[] = $this->check('الاتصالات الحالية', self::NA, 'تعذّر القياس');
        }

        return $out;
    }

    // ───────────────────────────── 6) Laravel ──────────────────────────────

    private function checkLaravel(): array
    {
        $out = [];

        $out[] = $this->check('الإصدارات', self::OK, 'Laravel ' . app()->version() . ' • PHP ' . PHP_VERSION);

        $env = app()->environment();
        $debug = (bool) config('app.debug');
        if ($env === 'production' && $debug) {
            $out[] = $this->check('البيئة', self::WARN, "{$env} • Debug مفعّل ⚠", 'يُفضّل إغلاق APP_DEBUG في الإنتاج');
            $this->recommend('APP_DEBUG مفعّل في الإنتاج — اضبطه على false.');
        } else {
            $out[] = $this->check('البيئة', self::OK, "{$env} • Debug " . ($debug ? 'مفعّل' : 'مغلق'));
        }

        $out[] = ! empty(config('app.key'))
            ? $this->check('APP_KEY', self::OK, 'مضبوط ✓')
            : $this->check('APP_KEY', self::ERROR, 'مفقود', 'php artisan key:generate');
        if (empty(config('app.key'))) {
            $this->recommend('APP_KEY مفقود — التشفير والجلسات لن تعمل. شغّل key:generate.');
        }

        $out[] = $this->check('السائقون (Drivers)', self::OK, sprintf(
            'cache=%s • queue=%s • session=%s • db=%s',
            config('cache.default'), config('queue.default'), config('session.driver'), config('database.default')
        ));

        try {
            $out[] = $this->check('عدد المسارات', self::OK, (string) app('router')->getRoutes()->count() . ' مسار');
        } catch (\Throwable) {
            $out[] = $this->check('عدد المسارات', self::NA, 'تعذّر العدّ');
        }

        // Failed jobs
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
                $out[] = $failed === 0
                    ? $this->check('المهام الفاشلة', self::OK, 'لا يوجد')
                    : $this->check('المهام الفاشلة', self::WARN, "{$failed} مهمة فاشلة", 'queue:retry all');
                if ($failed > 0) {
                    $this->recommend("يوجد {$failed} مهمة فاشلة — راجعها أو أعد محاولتها.");
                }
            } else {
                $out[] = $this->check('المهام الفاشلة', self::NA, 'لا يوجد جدول failed_jobs');
            }
        } catch (\Throwable $e) {
            $out[] = $this->check('المهام الفاشلة', self::NA, 'تعذّر', $e->getMessage());
        }

        // Storage link (the app also has a /storage route fallback)
        $linked = is_link(public_path('storage')) || file_exists(public_path('storage'));
        $out[] = $linked
            ? $this->check('storage link', self::OK, 'موجود ✓')
            : $this->check('storage link', self::WARN, 'مفقود', 'php artisan storage:link');

        // Writable paths
        $paths = [
            'storage/app'            => storage_path('app'),
            'storage/framework'      => storage_path('framework'),
            'storage/logs'           => storage_path('logs'),
            'bootstrap/cache'        => base_path('bootstrap/cache'),
        ];
        $bad = [];
        foreach ($paths as $name => $p) {
            if (! is_dir($p) || ! is_writable($p)) {
                $bad[] = $name;
            }
        }
        $out[] = empty($bad)
            ? $this->check('صلاحيات الكتابة', self::OK, 'كل المجلدات قابلة للكتابة ✓')
            : $this->check('صلاحيات الكتابة', self::ERROR, 'غير قابلة للكتابة: ' . implode('، ', $bad));
        if (! empty($bad)) {
            $this->recommend('مجلدات غير قابلة للكتابة (' . implode('، ', $bad) . ') — صحّح الصلاحيات (chmod -R 775).');
        }

        return $out;
    }

    // ───────────────────────────── 8) Storage ──────────────────────────────

    private function checkStorage(): array
    {
        $out = [];

        // .env may legitimately be absent on Railway (env injected). Don't alarm if the key is set.
        if (file_exists(base_path('.env'))) {
            $out[] = $this->check('ملف .env', self::OK, 'موجود');
        } else {
            $out[] = ! empty(config('app.key'))
                ? $this->check('ملف .env', self::OK, 'غير موجود كملف — المتغيرات محقونة من Railway ✓')
                : $this->check('ملف .env', self::ERROR, 'مفقود ولا توجد متغيرات بيئة');
        }

        // Logs size + oversized files
        $logsDir = storage_path('logs');
        $total = 0;
        $big = [];
        foreach (glob($logsDir . '/*') ?: [] as $f) {
            if (is_file($f)) {
                $sz = filesize($f) ?: 0;
                $total += $sz;
                if ($sz > 50 * 1024 * 1024) {
                    $big[] = basename($f) . ' (' . $this->humanBytes($sz) . ')';
                }
            }
        }
        $out[] = $this->check('حجم السجلات (logs)', $total > 200 * 1024 * 1024 ? self::WARN : self::OK, $this->humanBytes($total));
        if ($total > 200 * 1024 * 1024) {
            $this->recommend('مجلد logs متضخّم (' . $this->humanBytes($total) . ') — فعّل التدوير (daily) أو احذف القديم.');
        }
        $out[] = empty($big)
            ? $this->check('ملفات ضخمة', self::OK, 'لا يوجد ملفات سجل ضخمة')
            : $this->check('ملفات ضخمة', self::WARN, implode('، ', $big));

        return $out;
    }

    // ─────────────────────────────── 9) R2 ─────────────────────────────────

    private function checkR2(): array
    {
        $out = [];
        $disk   = config('filesystems.default', 'public');
        $bucket = config('filesystems.disks.r2.bucket');
        $hasKey = (bool) config('filesystems.disks.r2.key');

        $out[] = $hasKey
            ? $this->check('المفاتيح', self::OK, 'مضبوطة ✓ (مخفية)')
            : $this->check('المفاتيح', self::NA, 'غير مضبوطة — يُستخدم التخزين المحلي');

        if (! $hasKey || ! $bucket) {
            return $out;
        }

        $out[] = $this->check('الـBucket', self::OK, (string) $bucket);

        try {
            $name = '_health_' . uniqid() . '.txt';
            $t = microtime(true);
            Storage::disk($disk)->put($name, 'health-check');
            $exists = Storage::disk($disk)->exists($name);
            Storage::disk($disk)->delete($name);
            $ms = (int) round((microtime(true) - $t) * 1000);
            $out[] = $exists
                ? $this->check('اختبار رفع/حذف', self::OK, "نجح خلال {$ms}ms")
                : $this->check('اختبار رفع/حذف', self::ERROR, 'فشل التحقق من الرفع');
            if (! $exists) {
                $this->recommend('فشل الرفع إلى R2 — تحقّق من المفاتيح وصلاحيات الـbucket.');
            }
        } catch (\Throwable $e) {
            $out[] = $this->check('اختبار رفع/حذف', self::ERROR, 'خطأ', $e->getMessage());
            $this->recommend('خطأ في الاتصال بـ R2 — تحقّق من المفاتيح ونقطة النهاية (endpoint).');
        }

        return $out;
    }

    // ───────────────────────────── 5) Performance ──────────────────────────

    private function checkPerformance(): array
    {
        $r = $this->resources();
        $out = [];

        if ($r['load']) {
            $cpu = $r['cpu'] ?: 1;
            $l1 = $r['load'][0];
            $status = $l1 > ($cpu * 2) ? self::WARN : self::OK;
            $out[] = $this->check('متوسط الحِمل (Load)', $status, sprintf('%.2f / %.2f / %.2f (نوى: %d)', $r['load'][0], $r['load'][1], $r['load'][2], $cpu));
            if ($status === self::WARN) {
                $this->recommend('متوسط الحِمل مرتفع مقارنة بعدد النوى — راقب العمليات الثقيلة.');
            }
        } else {
            $out[] = $this->check('متوسط الحِمل (Load)', self::NA, 'غير متاح');
        }

        if ($r['ram']) {
            $status = $r['ram']['used_pct'] > 90 ? self::WARN : self::OK;
            $out[] = $this->check('الذاكرة (RAM)', $status, "{$r['ram']['used_pct']}% مستخدمة — " . $this->humanBytes($r['ram']['used']) . ' / ' . $this->humanBytes($r['ram']['total']));
            if ($status === self::WARN) {
                $this->recommend('استهلاك الذاكرة مرتفع (>90%) — فكّر في رفع خطة Railway.');
            }
        } else {
            $out[] = $this->check('الذاكرة (RAM)', self::NA, 'غير متاح');
        }

        if ($r['disk']) {
            $status = $r['disk']['used_pct'] > 90 ? self::WARN : self::OK;
            $out[] = $this->check('مساحة القرص', $status, "{$r['disk']['used_pct']}% مستخدمة — " . $this->humanBytes($r['disk']['used']) . ' / ' . $this->humanBytes($r['disk']['total']));
            if ($status === self::WARN) {
                $this->recommend('مساحة القرص ممتلئة (>90%) — نظّف الملفات المؤقتة/السجلات.');
            }
        } else {
            $out[] = $this->check('مساحة القرص', self::NA, 'غير متاح');
        }

        $out[] = $this->check('ذاكرة PHP', self::OK, 'مستخدمة الآن: ' . $this->humanBytes(memory_get_usage(true)) . ' • الذروة: ' . $this->humanBytes(memory_get_peak_usage(true)) . ' • الحد: ' . ini_get('memory_limit'));

        $out[] = $r['uptime']
            ? $this->check('زمن تشغيل الحاوية (Uptime)', self::OK, $r['uptime'])
            : $this->check('زمن تشغيل الحاوية (Uptime)', self::NA, 'غير متاح');

        return $out;
    }

    // ───────────────────────────── 4) Errors ───────────────────────────────

    private function checkErrors(): array
    {
        $out = [];
        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            $channel = config('logging.default');
            $out[] = $this->check('سجل Laravel', self::OK, "لا يوجد ملف سجل — القناة «{$channel}» (الأرجح stderr → سجلات Railway)");
            $out[] = $this->check('سجلات nginx / php-fpm / postgres', self::NA, 'غير متاحة كملفات على الاستضافة المُدارة (تُقرأ من لوحة Railway)');
            return $out;
        }

        $lines = $this->tail($logFile, 4000);
        $errors = 0;
        $warnings = 0;
        $samples = [];
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/\.(ERROR|CRITICAL|EMERGENCY|ALERT)/', $line)) {
                $errors++;
                if (count($samples) < 8) {
                    $samples[] = ['level' => self::ERROR, 'text' => mb_substr(trim($line), 0, 240)];
                }
            } elseif (preg_match('/\.WARNING/', $line)) {
                $warnings++;
                if (count($samples) < 8) {
                    $samples[] = ['level' => self::WARN, 'text' => mb_substr(trim($line), 0, 240)];
                }
            }
        }

        $status = $errors > 0 ? self::WARN : self::OK;
        $out[] = $this->check('سجل Laravel (آخر الأسطر)', $status, "أخطاء: {$errors} • تحذيرات: {$warnings}");
        $out[] = $this->check('سجلات nginx / php-fpm / postgres', self::NA, 'غير متاحة كملفات على الاستضافة المُدارة');

        // Attach samples as extra checks so they render in the report.
        foreach ($samples as $s) {
            $out[] = $this->check('↳', $s['level'], $s['text']);
        }
        if ($errors > 0) {
            $this->recommend("يوجد {$errors} خطأ حديث في سجل Laravel — راجع العيّنات أعلاه.");
        }

        return $out;
    }

    // ───────────────────────────── resources ───────────────────────────────

    private function resources(): array
    {
        return [
            'load'   => $this->loadAvg(),
            'cpu'    => $this->cpuCount(),
            'ram'    => $this->ram(),
            'disk'   => $this->disk(),
            'uptime' => $this->uptime(),
        ];
    }

    private function loadAvg(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            $l = @sys_getloadavg();
            if (is_array($l) && count($l) === 3) {
                return $l;
            }
        }
        return null;
    }

    private function cpuCount(): int
    {
        $info = @file_get_contents('/proc/cpuinfo');
        if ($info !== false) {
            return max(1, substr_count($info, 'processor'));
        }
        return 1;
    }

    private function ram(): ?array
    {
        $info = @file_get_contents('/proc/meminfo');
        if ($info === false) {
            return null;
        }
        $get = function (string $key) use ($info): ?int {
            if (preg_match('/^' . preg_quote($key, '/') . ':\s+(\d+)\s*kB/m', $info, $m)) {
                return ((int) $m[1]) * 1024;
            }
            return null;
        };
        $total = $get('MemTotal');
        $avail = $get('MemAvailable') ?? $get('MemFree');
        if (! $total || $avail === null) {
            return null;
        }
        $used = $total - $avail;
        return ['total' => $total, 'used' => $used, 'used_pct' => (int) round($used / $total * 100)];
    }

    private function disk(): ?array
    {
        $total = @disk_total_space('/');
        $free  = @disk_free_space('/');
        if (! $total || $free === false) {
            return null;
        }
        $used = $total - $free;
        return ['total' => (int) $total, 'used' => (int) $used, 'used_pct' => (int) round($used / $total * 100)];
    }

    private function uptime(): ?string
    {
        $u = @file_get_contents('/proc/uptime');
        if ($u === false) {
            return null;
        }
        $secs = (int) floatval(explode(' ', trim($u))[0] ?? 0);
        if ($secs <= 0) {
            return null;
        }
        $d = intdiv($secs, 86400);
        $h = intdiv($secs % 86400, 3600);
        $m = intdiv($secs % 3600, 60);
        return ($d ? "{$d}ي " : '') . "{$h}س {$m}د";
    }

    // ───────────────────────────── utilities ───────────────────────────────

    /** Read roughly the last $maxLines lines of a file without loading it all. */
    private function tail(string $file, int $maxLines = 2000): array
    {
        try {
            $size = filesize($file) ?: 0;
            $chunk = min($size, 512 * 1024); // last 512KB is plenty
            $fh = fopen($file, 'rb');
            if (! $fh) {
                return [];
            }
            fseek($fh, -$chunk, SEEK_END);
            $data = fread($fh, $chunk) ?: '';
            fclose($fh);
            $lines = preg_split('/\r?\n/', $data) ?: [];
            return array_slice($lines, -$maxLines);
        } catch (\Throwable) {
            return [];
        }
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / (1024 ** $i), $i ? 1 : 0) . ' ' . $units[$i];
    }
}
