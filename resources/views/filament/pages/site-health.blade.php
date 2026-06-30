<x-filament-panels::page>
    @php
        $report = $this->report ?? [];
        $s = $report['summary'] ?? ['ok' => 0, 'warn' => 0, 'error' => 0, 'na' => 0];
        $res = $report['resources'] ?? [];
        $style = [
            'ok'    => ['icon' => '✅', 'text' => 'text-success-600 dark:text-success-400'],
            'warn'  => ['icon' => '⚠️', 'text' => 'text-warning-600 dark:text-warning-400'],
            'error' => ['icon' => '❌', 'text' => 'text-danger-600 dark:text-danger-400'],
            'na'    => ['icon' => '➖', 'text' => 'text-gray-400 dark:text-gray-500'],
        ];
        $bar = fn ($pct) => $pct > 90 ? 'bg-danger-500' : ($pct > 75 ? 'bg-warning-500' : 'bg-success-500');
    @endphp

    {{-- ── Summary ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-950/40 p-4 text-center">
            <div class="text-2xl font-extrabold text-success-700 dark:text-success-400">{{ $s['ok'] }}</div>
            <div class="text-xs text-success-700/80 dark:text-success-400/80">✅ ناجح</div>
        </div>
        <div class="rounded-xl border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-950/40 p-4 text-center">
            <div class="text-2xl font-extrabold text-warning-700 dark:text-warning-400">{{ $s['warn'] }}</div>
            <div class="text-xs text-warning-700/80 dark:text-warning-400/80">⚠️ تحذير</div>
        </div>
        <div class="rounded-xl border border-danger-200 dark:border-danger-800 bg-danger-50 dark:bg-danger-950/40 p-4 text-center">
            <div class="text-2xl font-extrabold text-danger-700 dark:text-danger-400">{{ $s['error'] }}</div>
            <div class="text-xs text-danger-700/80 dark:text-danger-400/80">❌ فشل</div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-4 text-center">
            <div class="text-2xl font-extrabold text-gray-500">{{ $s['na'] }}</div>
            <div class="text-xs text-gray-400">➖ غير مطبّق</div>
        </div>
    </div>

    <p class="text-xs text-gray-400">
        آخر فحص: {{ $report['generated_at'] ?? '—' }} — استغرق {{ $report['duration_ms'] ?? 0 }}ms.
        كل قراءة في هذا التقرير مقيسة مباشرة من الخادم.
    </p>

    {{-- ── Resource bars ── --}}
    @if(!empty($res['ram']) || !empty($res['disk']))
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach(['ram' => 'الذاكرة (RAM)', 'disk' => 'مساحة القرص'] as $rk => $rlabel)
                @if(!empty($res[$rk]))
                    <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-semibold">{{ $rlabel }}</span>
                            <span class="text-gray-500">{{ $res[$rk]['used_pct'] }}%</span>
                        </div>
                        <div class="mt-2 h-2.5 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full rounded-full {{ $bar($res[$rk]['used_pct']) }}" style="width: {{ $res[$rk]['used_pct'] }}%"></div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- ── Sections ── --}}
    @foreach($report['sections'] ?? [] as $section)
        <div class="rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="px-4 py-2.5 bg-gray-50 dark:bg-white/5 font-bold text-sm">{{ $section['title'] }}</div>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach($section['checks'] as $c)
                    @php $st = $style[$c['status']] ?? $style['na']; @endphp
                    <div class="flex items-start gap-3 px-4 py-2.5 text-sm">
                        <span class="shrink-0 leading-6">{{ $st['icon'] }}</span>
                        <span class="w-44 shrink-0 text-gray-500 dark:text-gray-400 leading-6">{{ $c['label'] }}</span>
                        <span class="flex-1 font-medium leading-6 break-words {{ $st['text'] }}">
                            {{ $c['value'] }}
                            @if(!empty($c['note']))
                                <span class="text-gray-400 font-normal">— {{ $c['note'] }}</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- ── Recommendations ── --}}
    @if(!empty($report['recommendations']))
        <div class="rounded-xl border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-950/40 p-4">
            <div class="font-bold text-warning-700 dark:text-warning-400 mb-2">🛠️ توصيات الإصلاح</div>
            <ol class="list-decimal ms-5 space-y-1 text-sm text-warning-800 dark:text-warning-300">
                @foreach($report['recommendations'] as $rec)
                    <li>{{ $rec }}</li>
                @endforeach
            </ol>
        </div>
    @else
        <div class="rounded-xl border border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-950/40 p-4 text-success-700 dark:text-success-400 font-semibold">
            🎉 لا توجد توصيات — كل شيء سليم.
        </div>
    @endif

    <div class="text-xs text-gray-400">
        مركز التشخيص — استخدم «أدوات الإصلاح» بالأعلى للإجراءات (مسح الكاش، التحديثات، storage link…)، وكل إجراء يعيد الفحص ويعرض النتيجة. «تحميل التقرير» يحفظ نسخة .txt.
    </div>
</x-filament-panels::page>
