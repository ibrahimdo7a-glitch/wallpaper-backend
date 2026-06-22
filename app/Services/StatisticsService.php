<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StatisticsService
{
    private int $ttl = 300; // 5 minutes

    private function key(string $section, string $period = ''): string
    {
        return 'stats.' . $section . ($period ? '.' . $period : '');
    }

    // ─── Overview ────────────────────────────────────────────────────────────

    public function getOverview(string $period = '30d'): array
    {
        return Cache::remember($this->key('overview', $period), $this->ttl, function () use ($period) {
            [$start, $end] = $this->range($period);

            $totalWallpapers = DB::table('wallpapers')->whereNull('deleted_at')->count();
            $published       = DB::table('wallpapers')->whereNull('deleted_at')->where('status', 'published')->count();
            $pending         = DB::table('wallpapers')->whereNull('deleted_at')->where('status', 'pending')->count();
            $rejected        = DB::table('wallpapers')->whereNull('deleted_at')->where('status', 'rejected')->count();
            $totalDownloads  = DB::table('downloads')->count();
            $totalLikes      = DB::table('likes')->count();
            $totalCategories = DB::table('categories')->whereNull('deleted_at')->whereNull('parent_id')->count();
            $totalSub        = DB::table('categories')->whereNull('deleted_at')->whereNotNull('parent_id')->count();
            $totalUploaders  = DB::table('users')
                ->join('wallpapers', 'wallpapers.uploaded_by', '=', 'users.id')
                ->whereNull('wallpapers.deleted_at')
                ->distinct('users.id')->count('users.id');
            $totalUsers      = DB::table('users')->count();

            $periodDownloads = DB::table('downloads')->whereBetween('created_at', [$start, $end])->count();
            $periodLikes     = DB::table('likes')->whereBetween('created_at', [$start, $end])->count();
            $periodUploads   = DB::table('wallpapers')->whereNull('deleted_at')->whereBetween('created_at', [$start, $end])->count();

            return compact(
                'totalWallpapers', 'published', 'pending', 'rejected',
                'totalDownloads', 'totalLikes', 'totalCategories', 'totalSub',
                'totalUploaders', 'totalUsers',
                'periodDownloads', 'periodLikes', 'periodUploads'
            );
        });
    }

    // ─── Period Comparisons ──────────────────────────────────────────────────

    public function getComparisons(): array
    {
        return Cache::remember($this->key('comparisons'), $this->ttl, function () {
            $todayStart     = Carbon::today();
            $todayEnd       = Carbon::now();
            $yesterdayStart = Carbon::yesterday()->startOfDay();
            $yesterdayEnd   = Carbon::yesterday()->endOfDay();
            $weekStart      = Carbon::now()->startOfWeek();
            $lastWeekStart  = Carbon::now()->subWeek()->startOfWeek();
            $lastWeekEnd    = Carbon::now()->subWeek()->endOfWeek();
            $monthStart     = Carbon::now()->startOfMonth();
            $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd   = Carbon::now()->subMonth()->endOfMonth();

            $dl = fn($s, $e) => DB::table('downloads')->whereBetween('created_at', [$s, $e])->count();
            $lk = fn($s, $e) => DB::table('likes')->whereBetween('created_at', [$s, $e])->count();
            $up = fn($s, $e) => DB::table('wallpapers')->whereNull('deleted_at')->whereBetween('created_at', [$s, $e])->count();

            return [
                'today' => [
                    'downloads'           => $dl($todayStart, $todayEnd),
                    'yesterday_downloads' => $dl($yesterdayStart, $yesterdayEnd),
                    'likes'               => $lk($todayStart, $todayEnd),
                    'yesterday_likes'     => $lk($yesterdayStart, $yesterdayEnd),
                    'uploads'             => $up($todayStart, $todayEnd),
                    'yesterday_uploads'   => $up($yesterdayStart, $yesterdayEnd),
                ],
                'week' => [
                    'downloads'    => $dl($weekStart, Carbon::now()),
                    'prev_downloads' => $dl($lastWeekStart, $lastWeekEnd),
                    'likes'        => $lk($weekStart, Carbon::now()),
                    'prev_likes'   => $lk($lastWeekStart, $lastWeekEnd),
                    'uploads'      => $up($weekStart, Carbon::now()),
                    'prev_uploads' => $up($lastWeekStart, $lastWeekEnd),
                ],
                'month' => [
                    'downloads'    => $dl($monthStart, Carbon::now()),
                    'prev_downloads' => $dl($lastMonthStart, $lastMonthEnd),
                    'likes'        => $lk($monthStart, Carbon::now()),
                    'prev_likes'   => $lk($lastMonthStart, $lastMonthEnd),
                    'uploads'      => $up($monthStart, Carbon::now()),
                    'prev_uploads' => $up($lastMonthStart, $lastMonthEnd),
                ],
            ];
        });
    }

    // ─── Chart Data ──────────────────────────────────────────────────────────

    public function getChartData(string $period = '30d'): array
    {
        return Cache::remember($this->key('charts', $period), $this->ttl, function () use ($period) {
            $days      = $this->days($period);
            $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

            $dates = collect();
            for ($i = $days - 1; $i >= 0; $i--) {
                $dates->push(Carbon::now()->subDays($i)->format('Y-m-d'));
            }

            $dlRaw = DB::table('downloads')
                ->selectRaw("DATE(created_at) as d, COUNT(*) as n")
                ->where('created_at', '>=', $startDate)
                ->groupByRaw('DATE(created_at)')
                ->pluck('n', 'd');

            $lkRaw = DB::table('likes')
                ->selectRaw("DATE(created_at) as d, COUNT(*) as n")
                ->where('created_at', '>=', $startDate)
                ->groupByRaw('DATE(created_at)')
                ->pluck('n', 'd');

            $upRaw = DB::table('wallpapers')
                ->whereNull('deleted_at')
                ->selectRaw("DATE(created_at) as d, COUNT(*) as n")
                ->where('created_at', '>=', $startDate)
                ->groupByRaw('DATE(created_at)')
                ->pluck('n', 'd');

            $monthlyRaw = DB::table('downloads')
                ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as m, COUNT(*) as n")
                ->where('created_at', '>=', Carbon::now()->subMonths(11)->startOfMonth())
                ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM')")
                ->pluck('n', 'm');

            $monthLabels = [];
            $monthData   = [];
            for ($i = 11; $i >= 0; $i--) {
                $m           = Carbon::now()->subMonths($i);
                $monthLabels[] = $m->translatedFormat('M Y');
                $monthData[]   = (int) ($monthlyRaw[$m->format('Y-m')] ?? 0);
            }

            return [
                'labels'      => $dates->map(fn($d) => Carbon::parse($d)->format('d/m'))->values()->toArray(),
                'downloads'   => $dates->map(fn($d) => (int) ($dlRaw[$d] ?? 0))->values()->toArray(),
                'likes'       => $dates->map(fn($d) => (int) ($lkRaw[$d] ?? 0))->values()->toArray(),
                'uploads'     => $dates->map(fn($d) => (int) ($upRaw[$d] ?? 0))->values()->toArray(),
                'monthLabels' => $monthLabels,
                'monthData'   => $monthData,
            ];
        });
    }

    // ─── Top Content ─────────────────────────────────────────────────────────

    public function getTopContent(): array
    {
        return Cache::remember($this->key('top'), $this->ttl, function () {
            $disk = config('filesystems.default', 'public');

            $fmt = fn($rows) => collect($rows)->map(fn($w) => [
                'id'             => $w->id,
                'title'          => $w->title_ar ?: ($w->title_en ?: 'بدون عنوان'),
                'slug'           => $w->slug,
                'thumbnail_url'  => $w->thumbnail_file
                    ? Storage::disk($disk)->url($w->thumbnail_file)
                    : null,
                'downloads_count' => number_format($w->downloads_count),
                'likes_count'    => number_format($w->likes_count),
                'category'       => $w->category_name ?? '—',
                'created_at'     => $w->created_at ? Carbon::parse($w->created_at)->format('Y-m-d') : '—',
            ]);

            $base = DB::table('wallpapers as w')
                ->leftJoin('categories as c', 'c.id', '=', 'w.category_id')
                ->whereNull('w.deleted_at')
                ->where('w.status', 'published')
                ->select(
                    'w.id', 'w.title_ar', 'w.title_en', 'w.slug',
                    'w.thumbnail_file', 'w.downloads_count', 'w.likes_count',
                    'w.created_at', 'c.name_ar as category_name'
                );

            $topDownloaded = $fmt((clone $base)->orderByDesc('w.downloads_count')->limit(10)->get());
            $topLiked      = $fmt((clone $base)->orderByDesc('w.likes_count')->limit(10)->get());
            $latest        = $fmt((clone $base)->orderByDesc('w.created_at')->limit(10)->get());

            $topCategories = DB::table('categories as c')
                ->leftJoin('wallpapers as w', function ($j) {
                    $j->on('w.category_id', '=', 'c.id')->whereNull('w.deleted_at')->where('w.status', 'published');
                })
                ->whereNull('c.deleted_at')->whereNull('c.parent_id')
                ->groupBy('c.id', 'c.name_ar', 'c.name_en', 'c.slug')
                ->orderByRaw('COUNT(w.id) DESC')->limit(10)
                ->selectRaw('c.id, c.name_ar, c.name_en, c.slug, COUNT(w.id) as wallpapers_count, COALESCE(SUM(w.downloads_count),0) as downloads_sum')
                ->get();

            $topSubcategories = DB::table('categories as c')
                ->leftJoin('wallpapers as w', function ($j) {
                    $j->on('w.category_id', '=', 'c.id')->whereNull('w.deleted_at')->where('w.status', 'published');
                })
                ->whereNull('c.deleted_at')->whereNotNull('c.parent_id')
                ->groupBy('c.id', 'c.name_ar', 'c.name_en', 'c.slug')
                ->orderByRaw('COUNT(w.id) DESC')->limit(10)
                ->selectRaw('c.id, c.name_ar, c.name_en, c.slug, COUNT(w.id) as wallpapers_count, COALESCE(SUM(w.downloads_count),0) as downloads_sum')
                ->get();

            return compact('topDownloaded', 'topLiked', 'latest', 'topCategories', 'topSubcategories');
        });
    }

    // ─── Top Uploaders ───────────────────────────────────────────────────────

    public function getTopUploaders(): array
    {
        return Cache::remember($this->key('uploaders'), $this->ttl, function () {
            return DB::table('users as u')
                ->join('wallpapers as w', function ($j) {
                    $j->on('w.uploaded_by', '=', 'u.id')->whereNull('w.deleted_at');
                })
                ->groupBy('u.id', 'u.name', 'u.username')
                ->orderByRaw('COUNT(w.id) DESC')->limit(10)
                ->selectRaw(
                    "u.id, u.name, u.username,
                     COUNT(w.id) as wallpapers_count,
                     COALESCE(SUM(w.downloads_count),0) as downloads_sum,
                     COALESCE(SUM(w.likes_count),0) as likes_sum,
                     COUNT(CASE WHEN w.status = 'published' THEN 1 END) as published_count"
                )
                ->get()
                ->map(fn($u) => [
                    'name'      => $u->name,
                    'username'  => $u->username,
                    'wallpapers' => $u->wallpapers_count,
                    'published' => $u->published_count,
                    'downloads' => number_format($u->downloads_sum),
                    'likes'     => number_format($u->likes_sum),
                ])
                ->toArray();
        });
    }

    // ─── Moderation ──────────────────────────────────────────────────────────

    public function getModeration(): array
    {
        return Cache::remember($this->key('moderation'), $this->ttl, function () {
            $disk = config('filesystems.default', 'public');

            $pendingList = DB::table('wallpapers as w')
                ->leftJoin('categories as c', 'c.id', '=', 'w.category_id')
                ->whereNull('w.deleted_at')->where('w.status', 'pending')
                ->orderByDesc('w.created_at')->limit(20)
                ->selectRaw('w.id, w.title_ar, w.title_en, w.slug, w.thumbnail_file, w.created_at, c.name_ar as category_name')
                ->get()
                ->map(fn($w) => [
                    'title'         => $w->title_ar ?: ($w->title_en ?: 'بدون عنوان'),
                    'slug'          => $w->slug,
                    'thumbnail_url' => $w->thumbnail_file ? Storage::disk($disk)->url($w->thumbnail_file) : null,
                    'category'      => $w->category_name ?? 'بدون قسم',
                    'created_at'    => Carbon::parse($w->created_at)->diffForHumans(),
                ]);

            return [
                'pending_list'  => $pendingList,
                'pending_count' => DB::table('wallpapers')->whereNull('deleted_at')->where('status', 'pending')->count(),
                'rejected_count' => DB::table('wallpapers')->whereNull('deleted_at')->where('status', 'rejected')->count(),
                'no_category'   => DB::table('wallpapers')->whereNull('deleted_at')->whereNull('category_id')->count(),
                'no_thumbnail'  => DB::table('wallpapers')->whereNull('deleted_at')->whereNull('thumbnail_file')->count(),
                'no_original'   => DB::table('wallpapers')->whereNull('deleted_at')->whereNull('original_file')->count(),
            ];
        });
    }

    // ─── System Health ───────────────────────────────────────────────────────

    public function getHealth(): array
    {
        return Cache::remember($this->key('health'), $this->ttl, function () {
            $failedJob = null;
            $failedCount = 0;
            $pendingJobs = 0;
            try {
                $failedCount = DB::table('failed_jobs')->count();
                $failedJob   = DB::table('failed_jobs')->orderByDesc('failed_at')->first();
            } catch (\Throwable) {}
            try {
                $pendingJobs = DB::table('jobs')->count();
            } catch (\Throwable) {}

            $latestUpload = DB::table('wallpapers')
                ->whereNull('deleted_at')->whereNotNull('thumbnail_file')
                ->orderByDesc('created_at')->value('created_at');

            return [
                'r2_enabled'      => !empty(config('filesystems.disks.r2.key')) && !empty(config('filesystems.disks.r2.bucket')),
                'r2_bucket'       => config('filesystems.disks.r2.bucket') ?: '—',
                'disk'            => config('filesystems.default', 'public'),
                'files_original'  => DB::table('wallpapers')->whereNull('deleted_at')->whereNotNull('original_file')->count(),
                'files_thumbnail' => DB::table('wallpapers')->whereNull('deleted_at')->whereNotNull('thumbnail_file')->count(),
                'files_webp'      => DB::table('wallpapers')->whereNull('deleted_at')->whereNotNull('webp_file')->count(),
                'failed_count'    => $failedCount,
                'pending_jobs'    => $pendingJobs,
                'latest_failed'   => $failedJob ? [
                    'queue'     => $failedJob->queue,
                    'when'      => Carbon::parse($failedJob->failed_at)->diffForHumans(),
                    'exception' => mb_substr($failedJob->exception ?? '', 0, 200),
                ] : null,
                'latest_upload_at' => $latestUpload ? Carbon::parse($latestUpload)->diffForHumans() : 'لا يوجد',
            ];
        });
    }

    // ─── CSV Export ──────────────────────────────────────────────────────────

    public function buildCsvRows(string $period): array
    {
        $ov  = $this->getOverview($period);
        $cmp = $this->getComparisons();
        $ch  = $this->getChartData($period);
        $top = $this->getTopContent();

        $rows   = [];
        $rows[] = ['=== نظرة عامة ==='];
        $rows[] = ['البيان', 'القيمة'];
        foreach ([
            'إجمالي الخلفيات'       => $ov['totalWallpapers'],
            'الخلفيات المنشورة'      => $ov['published'],
            'الخلفيات في الانتظار'   => $ov['pending'],
            'الخلفيات المرفوضة'      => $ov['rejected'],
            'إجمالي التحميلات'       => $ov['totalDownloads'],
            'إجمالي الإعجابات'       => $ov['totalLikes'],
            'إجمالي الأقسام'         => $ov['totalCategories'],
            'إجمالي الأقسام الفرعية' => $ov['totalSub'],
            'عدد الرافعين'           => $ov['totalUploaders'],
            'عدد المستخدمين'         => $ov['totalUsers'],
        ] as $label => $val) {
            $rows[] = [$label, $val];
        }

        $rows[] = [];
        $rows[] = ['=== مقارنات الفترات ==='];
        $rows[] = ['الفترة', 'تحميلات الحالية', 'تحميلات السابقة', 'إعجابات الحالية', 'إعجابات السابقة', 'رفع الحالية', 'رفع السابقة'];
        $rows[] = ['اليوم مقابل الأمس',          $cmp['today']['downloads'],  $cmp['today']['yesterday_downloads'],  $cmp['today']['likes'],  $cmp['today']['yesterday_likes'],  $cmp['today']['uploads'],  $cmp['today']['yesterday_uploads']];
        $rows[] = ['هذا الأسبوع مقابل الماضي',   $cmp['week']['downloads'],   $cmp['week']['prev_downloads'],   $cmp['week']['likes'],   $cmp['week']['prev_likes'],   $cmp['week']['uploads'],   $cmp['week']['prev_uploads']];
        $rows[] = ['هذا الشهر مقابل الماضي',      $cmp['month']['downloads'],  $cmp['month']['prev_downloads'],  $cmp['month']['likes'],  $cmp['month']['prev_likes'],  $cmp['month']['uploads'],  $cmp['month']['prev_uploads']];

        $rows[] = [];
        $rows[] = ['=== بيانات يومية ==='];
        $rows[] = ['التاريخ', 'التحميلات', 'الإعجابات', 'الرفع'];
        foreach ($ch['labels'] as $i => $label) {
            $rows[] = [$label, $ch['downloads'][$i] ?? 0, $ch['likes'][$i] ?? 0, $ch['uploads'][$i] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['=== أكثر 10 خلفيات تحميلاً ==='];
        $rows[] = ['العنوان', 'القسم', 'التحميلات', 'الإعجابات'];
        foreach ($top['topDownloaded'] as $w) {
            $rows[] = [$w['title'], $w['category'], $w['downloads_count'], $w['likes_count']];
        }

        $rows[] = [];
        $rows[] = ['=== أكثر الأقسام نشاطاً ==='];
        $rows[] = ['القسم', 'الخلفيات', 'إجمالي التحميلات'];
        foreach ($top['topCategories'] as $c) {
            $rows[] = [$c->name_ar, $c->wallpapers_count, number_format($c->downloads_sum)];
        }

        return $rows;
    }

    // ─── Flush Cache ─────────────────────────────────────────────────────────

    public function flushAll(): void
    {
        $sections = ['overview', 'charts', 'top', 'uploaders', 'moderation', 'health', 'comparisons'];
        $periods  = ['today', '7d', '30d', '90d', 'year', 'all', 'month', 'last_month'];
        foreach ($sections as $s) {
            Cache::forget($this->key($s));
            foreach ($periods as $p) {
                Cache::forget($this->key($s, $p));
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function range(string $period): array
    {
        return match ($period) {
            'today'      => [Carbon::today(), Carbon::now()],
            '7d'         => [Carbon::now()->subDays(7), Carbon::now()],
            '30d'        => [Carbon::now()->subDays(30), Carbon::now()],
            '90d'        => [Carbon::now()->subDays(90), Carbon::now()],
            'year'       => [Carbon::now()->startOfYear(), Carbon::now()],
            'all'        => [Carbon::createFromTimestamp(0), Carbon::now()],
            'month'      => [Carbon::now()->startOfMonth(), Carbon::now()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            default      => [Carbon::now()->subDays(30), Carbon::now()],
        };
    }

    private function days(string $period): int
    {
        return match ($period) {
            'today'      => 1,
            '7d'         => 7,
            '30d'        => 30,
            '90d'        => 90,
            'year'       => (int) Carbon::now()->dayOfYear,
            'all'        => 365,
            'month'      => (int) Carbon::now()->format('j'),
            'last_month' => (int) Carbon::now()->subMonth()->daysInMonth,
            default      => 30,
        };
    }

}
