<x-filament-panels::page>

{{-- ==================== فلاتر الفترة الزمنية ==================== --}}
<div class="mb-6">
    <div class="flex flex-wrap gap-2">
        @foreach(['7d' => 'آخر ٧ أيام', '30d' => 'آخر ٣٠ يومًا', '90d' => 'آخر ٩٠ يومًا', 'year' => 'هذه السنة', 'all' => 'كل الوقت'] as $value => $label)
            <button
                wire:click="setPeriod('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium transition-colors
                    {{ $period === $value
                        ? 'bg-primary-600 text-white shadow'
                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>

{{-- ==================== ١. البطاقات الإجمالية ==================== --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">

    @php
    $cards = [
        ['label' => 'إجمالي الخلفيات', 'value' => number_format($overview['totalWallpapers'] ?? 0),   'icon' => 'photo',           'color' => 'blue'],
        ['label' => 'منشور',           'value' => number_format($overview['published'] ?? 0),          'icon' => 'check-circle',    'color' => 'green'],
        ['label' => 'قيد الانتظار',    'value' => number_format($overview['pending'] ?? 0),            'icon' => 'clock',           'color' => 'yellow'],
        ['label' => 'التحميلات',       'value' => number_format($overview['totalDownloads'] ?? 0),     'icon' => 'arrow-down-tray', 'color' => 'indigo'],
        ['label' => 'الإعجابات',       'value' => number_format($overview['totalLikes'] ?? 0),         'icon' => 'heart',           'color' => 'pink'],
        ['label' => 'الأقسام الرئيسية','value' => number_format($overview['totalCategories'] ?? 0),    'icon' => 'folder',          'color' => 'orange'],
        ['label' => 'الأقسام الفرعية', 'value' => number_format($overview['totalSub'] ?? 0),           'icon' => 'folder-open',     'color' => 'teal'],
        ['label' => 'الرافعون',        'value' => number_format($overview['totalUploaders'] ?? 0),     'icon' => 'user-group',      'color' => 'purple'],
        ['label' => 'المستخدمون',      'value' => number_format($overview['totalUsers'] ?? 0),         'icon' => 'users',           'color' => 'cyan'],
        ['label' => 'تحميل في الفترة', 'value' => number_format($overview['periodDownloads'] ?? 0),   'icon' => 'calendar',        'color' => 'rose'],
    ];
    $colorMap = [
        'blue'   => 'bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        'green'  => 'bg-green-50 dark:bg-green-950 text-green-600 dark:text-green-400 border-green-200 dark:border-green-800',
        'yellow' => 'bg-yellow-50 dark:bg-yellow-950 text-yellow-600 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800',
        'indigo' => 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
        'pink'   => 'bg-pink-50 dark:bg-pink-950 text-pink-600 dark:text-pink-400 border-pink-200 dark:border-pink-800',
        'purple' => 'bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400 border-purple-200 dark:border-purple-800',
        'orange' => 'bg-orange-50 dark:bg-orange-950 text-orange-600 dark:text-orange-400 border-orange-200 dark:border-orange-800',
        'teal'   => 'bg-teal-50 dark:bg-teal-950 text-teal-600 dark:text-teal-400 border-teal-200 dark:border-teal-800',
        'cyan'   => 'bg-cyan-50 dark:bg-cyan-950 text-cyan-600 dark:text-cyan-400 border-cyan-200 dark:border-cyan-800',
        'rose'   => 'bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-800',
    ];
    @endphp

    @foreach($cards as $card)
    <div class="rounded-2xl border p-4 {{ $colorMap[$card['color']] }}">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium opacity-70">{{ $card['label'] }}</span>
            <x-heroicon-o-{{ $card['icon'] }} class="w-5 h-5 opacity-60" />
        </div>
        <div class="text-2xl font-bold">{{ $card['value'] }}</div>
    </div>
    @endforeach
</div>

{{-- ==================== ٢. مقارنة الفترات ==================== --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    @php
    // comparisons structure: today/week/month with downloads,likes,uploads and their prev counterparts
    $cmpSections = [
        [
            'title'   => 'اليوم مقابل أمس',
            'key'     => 'today',
            'metrics' => [
                ['label' => 'تحميلات', 'curr_key' => 'downloads',  'prev_key' => 'yesterday_downloads'],
                ['label' => 'إعجابات', 'curr_key' => 'likes',      'prev_key' => 'yesterday_likes'],
                ['label' => 'رفع',     'curr_key' => 'uploads',    'prev_key' => 'yesterday_uploads'],
            ],
        ],
        [
            'title'   => 'هذا الأسبوع مقابل الماضي',
            'key'     => 'week',
            'metrics' => [
                ['label' => 'تحميلات', 'curr_key' => 'downloads',  'prev_key' => 'prev_downloads'],
                ['label' => 'إعجابات', 'curr_key' => 'likes',      'prev_key' => 'prev_likes'],
                ['label' => 'رفع',     'curr_key' => 'uploads',    'prev_key' => 'prev_uploads'],
            ],
        ],
        [
            'title'   => 'هذا الشهر مقابل الماضي',
            'key'     => 'month',
            'metrics' => [
                ['label' => 'تحميلات', 'curr_key' => 'downloads',  'prev_key' => 'prev_downloads'],
                ['label' => 'إعجابات', 'curr_key' => 'likes',      'prev_key' => 'prev_likes'],
                ['label' => 'رفع',     'curr_key' => 'uploads',    'prev_key' => 'prev_uploads'],
            ],
        ],
    ];
    @endphp

    @foreach($cmpSections as $section)
    @php $cmp = $comparisons[$section['key']] ?? []; @endphp
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">{{ $section['title'] }}</h3>
        <div class="space-y-3">
            @foreach($section['metrics'] as $m)
            @php
                $curr = $cmp[$m['curr_key']] ?? 0;
                $prev = $cmp[$m['prev_key']] ?? 0;
                $pct  = $prev > 0 ? round((($curr - $prev) / $prev) * 100) : ($curr > 0 ? 100 : 0);
                $up   = $pct >= 0;
            @endphp
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $m['label'] }}</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold dark:text-white">{{ number_format($curr) }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded {{ $up ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' }}">
                        {{ $up ? '▲' : '▼' }} {{ abs($pct) }}%
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>

{{-- ==================== ٣. الرسوم البيانية ==================== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

    {{-- رسم التحميلات / الإعجابات --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">التحميلات والإعجابات يوميًا</h3>
        <canvas id="statsActivityChart" height="220"></canvas>
    </div>

    {{-- رسم مقارنة شهرية --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">مقارنة شهرية (١٢ شهر)</h3>
        <canvas id="statsMonthlyChart" height="220"></canvas>
    </div>

    {{-- رسم الرفع --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 lg:col-span-2">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">الخلفيات المرفوعة يوميًا</h3>
        <canvas id="statsUploadsChart" height="120"></canvas>
    </div>
</div>

{{-- ==================== ٤. المحتوى الأعلى أداءً ==================== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

    {{-- أكثر تحميلًا --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">⬇️ الأكثر تحميلًا</h3>
        <div class="space-y-2">
            @forelse($topContent['topDownloaded'] ?? [] as $i => $w)
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 w-4">{{ $i + 1 }}</span>
                @if($w['thumbnail_url'] ?? null)
                    <img src="{{ $w['thumbnail_url'] }}" alt="" class="w-10 h-7 rounded object-cover shrink-0">
                @else
                    <div class="w-10 h-7 rounded bg-gray-200 dark:bg-gray-700 shrink-0"></div>
                @endif
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate">{{ $w['title'] ?? '—' }}</div>
                    <div class="text-[10px] text-gray-400">{{ $w['category'] ?? '' }}</div>
                </div>
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 shrink-0">{{ $w['downloads_count'] }}</span>
            </div>
            @empty
            <p class="text-xs text-gray-400">لا توجد بيانات</p>
            @endforelse
        </div>
    </div>

    {{-- أكثر إعجابًا --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">❤️ الأكثر إعجابًا</h3>
        <div class="space-y-2">
            @forelse($topContent['topLiked'] ?? [] as $i => $w)
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 w-4">{{ $i + 1 }}</span>
                @if($w['thumbnail_url'] ?? null)
                    <img src="{{ $w['thumbnail_url'] }}" alt="" class="w-10 h-7 rounded object-cover shrink-0">
                @else
                    <div class="w-10 h-7 rounded bg-gray-200 dark:bg-gray-700 shrink-0"></div>
                @endif
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate">{{ $w['title'] ?? '—' }}</div>
                    <div class="text-[10px] text-gray-400">{{ $w['category'] ?? '' }}</div>
                </div>
                <span class="text-xs font-bold text-pink-600 dark:text-pink-400 shrink-0">{{ $w['likes_count'] }}</span>
            </div>
            @empty
            <p class="text-xs text-gray-400">لا توجد بيانات</p>
            @endforelse
        </div>
    </div>

    {{-- أحدث الخلفيات --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">✨ أحدث الخلفيات</h3>
        <div class="space-y-2">
            @forelse($topContent['latest'] ?? [] as $w)
            <div class="flex items-center gap-3">
                @if($w['thumbnail_url'] ?? null)
                    <img src="{{ $w['thumbnail_url'] }}" alt="" class="w-10 h-7 rounded object-cover shrink-0">
                @else
                    <div class="w-10 h-7 rounded bg-gray-200 dark:bg-gray-700 shrink-0"></div>
                @endif
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate">{{ $w['title'] ?? '—' }}</div>
                    <div class="text-[10px] text-gray-400">{{ $w['created_at'] ?? '' }}</div>
                </div>
                <span class="text-[10px] text-gray-400 shrink-0">{{ $w['category'] ?? '' }}</span>
            </div>
            @empty
            <p class="text-xs text-gray-400">لا توجد بيانات</p>
            @endforelse
        </div>
    </div>

    {{-- أفضل الأقسام --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">📂 أفضل الأقسام</h3>
        <div class="space-y-3">
            @forelse($topContent['topCategories'] ?? [] as $i => $cat)
            @php
                $maxDl = ($topContent['topCategories'][0]->downloads_sum ?? 1) ?: 1;
                $pct   = min(100, round(($cat->downloads_sum / $maxDl) * 100));
            @endphp
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-gray-700 dark:text-gray-300">{{ $cat->name_ar }}</span>
                    <span class="text-gray-500">{{ number_format($cat->downloads_sum) }} تحميل</span>
                </div>
                <div class="w-full h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-1.5 rounded-full bg-indigo-500" style="width:{{ $pct }}%"></div>
                </div>
            </div>
            @empty
            <p class="text-xs text-gray-400">لا توجد بيانات</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ==================== ٥. أفضل الرافعين ==================== --}}
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">👤 أفضل الرافعين</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <th class="pb-2 text-right font-medium">#</th>
                    <th class="pb-2 text-right font-medium">الاسم</th>
                    <th class="pb-2 text-right font-medium">الخلفيات</th>
                    <th class="pb-2 text-right font-medium">التحميلات</th>
                    <th class="pb-2 text-right font-medium">الإعجابات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($uploaders as $i => $u)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                    <td class="py-2 text-gray-400 text-xs">{{ $i + 1 }}</td>
                    <td class="py-2 font-medium text-gray-800 dark:text-gray-200">
                        {{ $u['name'] ?? '—' }}
                        @if($u['username'] ?? null)
                            <span class="text-[10px] text-gray-400">&#64;{{ $u['username'] }}</span>
                        @endif
                    </td>
                    <td class="py-2 text-indigo-600 dark:text-indigo-400 font-semibold">{{ number_format($u['wallpapers'] ?? 0) }}</td>
                    <td class="py-2 text-gray-600 dark:text-gray-300">{{ $u['downloads'] ?? 0 }}</td>
                    <td class="py-2 text-pink-600 dark:text-pink-400">{{ $u['likes'] ?? 0 }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="py-4 text-center text-gray-400 text-xs">لا توجد بيانات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ==================== ٦. قائمة الإشراف ==================== --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

    {{-- إحصاء المشاكل --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">🔍 مشاكل المحتوى</h3>
        <div class="space-y-3">
            @php
            $issues = [
                ['label' => 'بدون صورة مصغرة', 'key' => 'no_thumbnail'],
                ['label' => 'بدون قسم',         'key' => 'no_category'],
                ['label' => 'بدون رابط أصلي',   'key' => 'no_original'],
            ];
            @endphp
            @foreach($issues as $issue)
            @php $cnt = $moderation[$issue['key']] ?? 0; @endphp
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-600 dark:text-gray-400">{{ $issue['label'] }}</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full
                    {{ $cnt > 0
                        ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'
                        : 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' }}">
                    {{ $cnt }}
                </span>
            </div>
            @endforeach

            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-between">
                <span class="text-xs text-gray-500">قيد الانتظار</span>
                <span class="text-sm font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($moderation['pending_count'] ?? 0) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-xs text-gray-500">مرفوض</span>
                <span class="text-sm font-bold text-red-600 dark:text-red-400">{{ number_format($moderation['rejected_count'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    {{-- قائمة الانتظار --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 lg:col-span-2">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">⏳ قائمة الانتظار</h3>
        <div class="space-y-2 max-h-64 overflow-y-auto">
            @forelse($moderation['pending_list'] ?? [] as $w)
            <div class="flex items-center gap-3 py-1.5 border-b border-gray-100 dark:border-gray-700 last:border-0">
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate">{{ $w['title'] ?? 'بدون عنوان' }}</div>
                    <div class="text-[10px] text-gray-400">{{ $w['category'] ?? '' }} · {{ $w['created_at'] ?? '' }}</div>
                </div>
                <span class="text-[10px] text-yellow-600 dark:text-yellow-400 shrink-0 font-medium">انتظار</span>
            </div>
            @empty
            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                <span class="text-xs">لا توجد خلفيات في الانتظار</span>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ==================== ٧. صحة النظام ==================== --}}
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">🖥️ صحة النظام</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">تخزين {{ $health['r2_bucket'] ?? 'R2' }}</div>
            <div class="text-lg font-bold {{ ($health['r2_enabled'] ?? false) ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                {{ ($health['r2_enabled'] ?? false) ? '✅ مُفعَّل' : '⚠️ محلي' }}
            </div>
        </div>
        <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">الصور المصغرة</div>
            <div class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ number_format($health['files_thumbnail'] ?? 0) }}</div>
        </div>
        <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">وظائف فاشلة</div>
            <div class="text-lg font-bold {{ ($health['failed_count'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                {{ number_format($health['failed_count'] ?? 0) }}
            </div>
        </div>
        <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">آخر رفع</div>
            <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $health['latest_upload_at'] ?? '—' }}</div>
        </div>
    </div>
    @if(($health['latest_failed'] ?? null))
    <div class="mt-3 text-xs text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-950 rounded p-2">
        آخر فشل في الوظائف ({{ $health['latest_failed']['when'] ?? '' }}): {{ $health['latest_failed']['exception'] ?? '' }}
    </div>
    @endif
</div>

{{-- ==================== Chart.js ==================== --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    let activityChart = null;
    let monthlyChart  = null;
    let uploadsChart  = null;

    const isDark = () => document.documentElement.classList.contains('dark');
    const gridColor  = () => isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const labelColor = () => isDark() ? '#9ca3af' : '#6b7280';

    function buildCharts(chartData) {
        const days       = chartData.labels      || [];
        const downloads  = chartData.downloads   || [];
        const likes      = chartData.likes       || [];
        const uploads    = chartData.uploads     || [];
        const months     = chartData.monthLabels || [];
        const monthDl    = chartData.monthData   || [];
        const monthLikes = chartData.monthLikes  || [];

        const axisOpts = {
            grid: { color: gridColor() },
            ticks: { color: labelColor(), maxRotation: 45, font: { size: 11 } },
        };

        // --- Activity chart ---
        if (activityChart) activityChart.destroy();
        const actCtx = document.getElementById('statsActivityChart');
        if (actCtx) {
            activityChart = new Chart(actCtx, {
                type: 'line',
                data: {
                    labels: days,
                    datasets: [
                        {
                            label: 'تحميلات',
                            data: downloads,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.12)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: days.length > 30 ? 0 : 3,
                        },
                        {
                            label: 'إعجابات',
                            data: likes,
                            borderColor: '#ec4899',
                            backgroundColor: 'rgba(236,72,153,0.08)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: days.length > 30 ? 0 : 3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { color: labelColor(), font: { size: 11 } } } },
                    scales: { x: axisOpts, y: { ...axisOpts, beginAtZero: true } },
                },
            });
        }

        // --- Monthly comparison chart ---
        if (monthlyChart) monthlyChart.destroy();
        const monCtx = document.getElementById('statsMonthlyChart');
        if (monCtx) {
            monthlyChart = new Chart(monCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'تحميلات',
                            data: monthDl,
                            backgroundColor: 'rgba(99,102,241,0.7)',
                            borderRadius: 4,
                        },
                        {
                            label: 'إعجابات',
                            data: monthLikes,
                            backgroundColor: 'rgba(236,72,153,0.6)',
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: labelColor(), font: { size: 11 } } } },
                    scales: { x: axisOpts, y: { ...axisOpts, beginAtZero: true } },
                },
            });
        }

        // --- Uploads chart ---
        if (uploadsChart) uploadsChart.destroy();
        const uplCtx = document.getElementById('statsUploadsChart');
        if (uplCtx) {
            uploadsChart = new Chart(uplCtx, {
                type: 'bar',
                data: {
                    labels: days,
                    datasets: [{
                        label: 'خلفيات مرفوعة',
                        data: uploads,
                        backgroundColor: 'rgba(34,197,94,0.65)',
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: labelColor(), font: { size: 11 } } } },
                    scales: { x: { ...axisOpts, ticks: { ...axisOpts.ticks, maxTicksLimit: 12 } }, y: { ...axisOpts, beginAtZero: true } },
                },
            });
        }
    }

    // Initial data from PHP
    const initialData = @json($chartData);
    document.addEventListener('DOMContentLoaded', () => buildCharts(initialData));

    // Re-init on Livewire update (period change / refresh)
    document.addEventListener('statsChartsInit', (e) => {
        buildCharts(e.detail.chartData ?? e.detail[0]?.chartData ?? initialData);
    });
})();
</script>
@endpush

</x-filament-panels::page>
