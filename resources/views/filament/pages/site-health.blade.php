<x-filament-panels::page>
    @php
        $report = $this->report ?? [];
        $s   = $report['summary'] ?? ['ok' => 0, 'warn' => 0, 'error' => 0, 'na' => 0];
        $res = $report['resources'] ?? [];
        $icons = ['ok' => '✅', 'warn' => '⚠️', 'error' => '❌', 'na' => '➖'];
        $bar = fn ($pct) => $pct > 90 ? '#ef4444' : ($pct > 75 ? '#f59e0b' : '#22c55e');
    @endphp

    <style>
        .hz-sumgrid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        @media (max-width:640px){ .hz-sumgrid{ grid-template-columns:repeat(2,1fr); } }
        .hz-sum { border-radius:14px; padding:12px; text-align:center; border:1px solid #e5e7eb; }
        .dark .hz-sum { border-color:rgba(255,255,255,.08); }
        .hz-sum .n { font-size:26px; font-weight:900; line-height:1; }
        .hz-sum .l { font-size:11px; margin-top:4px; opacity:.85; }

        .hz-head { font-size:12.5px; font-weight:800; color:#6b7280; margin:2px; }
        .dark .hz-head { color:#9aa6bd; }
        .hz-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(195px,1fr)); gap:9px; margin-top:7px; }
        .hz-tile { border:1px solid #e5e7eb; border-radius:13px; padding:10px 12px; background:#fff;
                   display:flex; flex-direction:column; gap:3px; min-width:0; }
        .dark .hz-tile { border-color:rgba(255,255,255,.08); background:rgba(255,255,255,.03); }
        .hz-tile.ok    { border-inline-start:3px solid #22c55e; }
        .hz-tile.warn  { border-inline-start:3px solid #f59e0b; }
        .hz-tile.error { border-inline-start:3px solid #ef4444; }
        .hz-tile.na    { border-inline-start:3px solid #cbd5e1; }
        .hz-l { font-size:11px; color:#6b7280; display:flex; align-items:center; gap:5px; }
        .dark .hz-l { color:#9aa6bd; }
        .hz-v { font-size:13px; font-weight:700; line-height:1.35; word-break:break-word; }
        .hz-n { font-size:10.5px; color:#9ca3af; font-weight:400; word-break:break-word; }
        .hz-v.ok{color:#16a34a;} .hz-v.warn{color:#d97706;} .hz-v.error{color:#dc2626;} .hz-v.na{color:#94a3b8;}
    </style>

    {{-- ── Summary ── --}}
    <div class="hz-sumgrid">
        <div class="hz-sum" style="border-color:#22c55e55;background:#22c55e12;">
            <div class="n" style="color:#16a34a;">{{ $s['ok'] }}</div><div class="l" style="color:#16a34a;">✅ ناجح</div>
        </div>
        <div class="hz-sum" style="border-color:#f59e0b55;background:#f59e0b12;">
            <div class="n" style="color:#d97706;">{{ $s['warn'] }}</div><div class="l" style="color:#d97706;">⚠️ تحذير</div>
        </div>
        <div class="hz-sum" style="border-color:#ef444455;background:#ef444412;">
            <div class="n" style="color:#dc2626;">{{ $s['error'] }}</div><div class="l" style="color:#dc2626;">❌ فشل</div>
        </div>
        <div class="hz-sum" style="background:rgba(148,163,184,.08);">
            <div class="n" style="color:#94a3b8;">{{ $s['na'] }}</div><div class="l" style="color:#94a3b8;">➖ غير مطبّق</div>
        </div>
    </div>

    <p class="text-xs text-gray-400">
        آخر فحص: {{ $report['generated_at'] ?? '—' }} — استغرق {{ $report['duration_ms'] ?? 0 }}ms.
        كل قراءة مقيسة مباشرة من الخادم.
    </p>

    {{-- ── Resource bars ── --}}
    @if(!empty($res['ram']) || !empty($res['disk']))
        <div class="hz-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));">
            @foreach(['ram' => 'الذاكرة (RAM)', 'disk' => 'مساحة القرص'] as $rk => $rlabel)
                @if(!empty($res[$rk]))
                    <div class="hz-tile">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold">{{ $rlabel }}</span>
                            <span class="text-gray-500">{{ $res[$rk]['used_pct'] }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ $res[$rk]['used_pct'] }}%;background:{{ $bar($res[$rk]['used_pct']) }};"></div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- ── Sections (same order, now as compact tiles) ── --}}
    @foreach($report['sections'] ?? [] as $section)
        <div>
            <div class="hz-head">{{ $section['title'] }}</div>
            <div class="hz-grid">
                @foreach($section['checks'] as $c)
                    @php $st = in_array($c['status'], ['ok','warn','error','na'], true) ? $c['status'] : 'na'; @endphp
                    <div class="hz-tile {{ $st }}">
                        <div class="hz-l">{{ $icons[$st] }} {{ $c['label'] }}</div>
                        <div class="hz-v {{ $st }}">{{ $c['value'] }}</div>
                        @if(!empty($c['note']))
                            <div class="hz-n">{{ $c['note'] }}</div>
                        @endif
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
        مركز التشخيص — استخدم «أدوات الإصلاح» بالأعلى للإجراءات، وكل إجراء يعيد الفحص ويعرض النتيجة. «تحميل التقرير» يحفظ نسخة .txt.
    </div>
</x-filament-panels::page>
