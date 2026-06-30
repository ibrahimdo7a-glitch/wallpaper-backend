<x-filament-panels::page>
    @php
        $r = $this->report ?? [];
        $online = $r['online'] ?? ['total' => 0, 'members' => 0, 'guests' => 0];
        $series = $r['series'] ?? ['labels' => [], 'visitors' => [], 'pageviews' => []];
        $sources = $r['sources'] ?? [];
        $srcTotal = array_sum($sources) ?: 1;
        $srcLabels = [
            'google' => 'Google', 'telegram' => 'تلجرام', 'instagram' => 'انستقرام',
            'facebook' => 'فيسبوك', 'x' => 'X (تويتر)', 'youtube' => 'يوتيوب',
            'whatsapp' => 'واتساب', 'reddit' => 'Reddit', 'direct' => 'مباشر', 'referral' => 'إحالات', 'other' => 'أخرى',
        ];
        $maxV = max(array_merge([1], $series['visitors'] ?? []));
    @endphp

    {{-- Period selector --}}
    <div class="flex flex-wrap gap-2">
        @foreach($this->periods as $k => $label)
            <button type="button" wire:click="setPeriod('{{ $k }}')"
                class="px-4 py-1.5 rounded-full text-sm font-medium transition
                    {{ $this->period === $k ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($r['cards'] ?? [] as $card)
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $card['icon'] }} {{ $card['label'] }}</span>
                </div>
                <div class="mt-1 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($card['value']) }}</div>
                @if(!is_null($card['delta'] ?? null))
                    @php $up = $card['delta'] >= 0; @endphp
                    <div class="mt-1 text-xs font-semibold {{ $up ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $up ? '▲ +' : '▼ ' }}{{ $card['delta'] }}%
                        <span class="text-gray-400 font-normal">مقارنة بالفترة السابقة</span>
                    </div>
                @elseif(!($r['has_prev'] ?? true))
                    <div class="mt-1 text-xs text-gray-400">إجمالي تراكمي</div>
                @else
                    <div class="mt-1 text-xs text-gray-400">—</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Online now --}}
    <div class="rounded-xl border border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-950/30 p-4">
        <div class="flex items-center flex-wrap gap-x-8 gap-y-2">
            <div>
                <div class="text-xs text-success-700 dark:text-success-400 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-success-500 animate-pulse"></span> متواجدون الآن
                </div>
                <div class="text-3xl font-extrabold text-success-700 dark:text-success-400">{{ number_format($online['total']) }}</div>
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <div>👤 أعضاء مسجّلون: <b>{{ $online['members'] }}</b></div>
                <div>🌐 زوّار غير مسجّلين: <b>{{ $online['guests'] }}</b></div>
            </div>
            <div class="text-xs text-gray-400">آخر ٣ دقائق — اضغط «تحديث» للحظي</div>
        </div>
    </div>

    {{-- Visitors chart --}}
    <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-sm">📈 الزوّار يوميًا ({{ count($series['visitors']) }} يوم)</h2>
            <span class="text-xs text-gray-400">إجمالي المشاهدات بالفترة: {{ number_format(array_sum($series['pageviews'])) }}</span>
        </div>
        <div class="flex items-end gap-1 h-40">
            @forelse($series['visitors'] as $i => $v)
                <div class="flex-1 flex flex-col items-center justify-end h-full group" title="{{ $series['labels'][$i] ?? '' }}: {{ $v }} زائر">
                    <span class="text-[10px] text-gray-400 opacity-0 group-hover:opacity-100">{{ $v }}</span>
                    <div class="w-full rounded-t bg-primary-500/80 hover:bg-primary-500 transition-all" style="height: {{ max(2, (int) round($v / $maxV * 100)) }}%"></div>
                </div>
            @empty
                <p class="text-sm text-gray-400">لا توجد بيانات بعد — ستظهر مع أول الزيارات.</p>
            @endforelse
        </div>
        @if(count($series['labels']))
            <div class="flex justify-between mt-2 text-[10px] text-gray-400">
                <span>{{ $series['labels'][0] ?? '' }}</span>
                <span>{{ $series['labels'][count($series['labels']) - 1] ?? '' }}</span>
            </div>
        @endif
    </div>

    <div class="grid md:grid-cols-2 gap-3">
        {{-- Traffic sources --}}
        <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
            <h2 class="font-bold text-sm mb-3">🚦 مصادر الزيارات</h2>
            @forelse($sources as $key => $count)
                @php $pct = round($count / $srcTotal * 100); @endphp
                <div class="mb-2">
                    <div class="flex justify-between text-xs mb-0.5">
                        <span class="text-gray-600 dark:text-gray-300">{{ $srcLabels[$key] ?? $key }}</span>
                        <span class="text-gray-400">{{ number_format($count) }} ({{ $pct }}%)</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">لا توجد بيانات مصادر بعد.</p>
            @endforelse
        </div>

        {{-- Members --}}
        <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
            <h2 class="font-bold text-sm mb-3">👥 الأعضاء</h2>
            <div class="grid grid-cols-4 gap-2 mb-3 text-center">
                @foreach(['today' => 'اليوم', 'week' => 'الأسبوع', 'month' => 'الشهر', 'active7' => 'نشِط (٧ي)'] as $k => $l)
                    <div class="rounded-lg bg-gray-50 dark:bg-white/5 py-2">
                        <div class="text-lg font-bold">{{ number_format($r['members'][$k] ?? 0) }}</div>
                        <div class="text-[10px] text-gray-400">{{ $l }}</div>
                    </div>
                @endforeach
            </div>
            <div class="space-y-1">
                @foreach($r['members']['recent'] ?? [] as $m)
                    <div class="flex items-center justify-between text-xs border-b border-gray-100 dark:border-white/5 py-1">
                        <span class="font-medium truncate">{{ $m['name'] ?: ($m['telegram_username'] ? '@' . $m['telegram_username'] : 'عضو') }}</span>
                        <span class="text-gray-400">{{ \Illuminate\Support\Carbon::parse($m['created_at'])->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Top pages --}}
    @php $tp = $r['top_pages'] ?? []; $tpMax = max(array_merge([1], array_values($tp))); @endphp
    <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
        <h2 class="font-bold text-sm mb-3">🔥 أكثر الصفحات زيارة — {{ $r['period_label'] ?? '' }}</h2>
        @forelse($tp as $path => $c)
            <div class="mb-2">
                <div class="flex justify-between text-xs mb-0.5">
                    <span class="truncate text-gray-600 dark:text-gray-300" dir="ltr">{{ $path }}</span>
                    <span class="text-gray-400 shrink-0 ms-2">{{ number_format($c) }}</span>
                </div>
                <div class="h-1.5 rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                    <div class="h-full rounded-full bg-primary-500" style="width: {{ round($c / $tpMax * 100) }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400">لا توجد بيانات صفحات بعد.</p>
        @endforelse
    </div>

    {{-- Geographic --}}
    <div class="grid md:grid-cols-2 gap-3">
        <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
            <h2 class="font-bold text-sm mb-3">🌍 أكثر الدول زيارة</h2>
            @php $tc = $r['top_countries'] ?? []; $tcMax = max(array_merge([1], array_values($tc))); @endphp
            @forelse($tc as $cc => $c)
                <div class="flex items-center gap-2 mb-2 text-sm">
                    <span class="text-lg">{{ \App\Services\AnalyticsService::flag($cc) }}</span>
                    <span class="w-8 text-gray-500">{{ $cc }}</span>
                    <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ round($c / $tcMax * 100) }}%"></div>
                    </div>
                    <span class="text-gray-400 w-10 text-end">{{ number_format($c) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">لا توجد بيانات جغرافية بعد (الدولة تأتي عبر Cloudflare).</p>
            @endforelse
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
            <h2 class="font-bold text-sm mb-3">👥 الأعضاء حسب الدولة</h2>
            @forelse($r['members_by_country'] ?? [] as $cc => $c)
                <div class="flex items-center justify-between mb-1.5 text-sm border-b border-gray-100 dark:border-white/5 pb-1">
                    <span>{{ \App\Services\AnalyticsService::flag($cc) }} <span class="text-gray-500">{{ $cc }}</span></span>
                    <span class="text-gray-400">{{ number_format($c) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">لا يوجد أعضاء بدولة محدّدة بعد.</p>
            @endforelse
        </div>
    </div>

    <div class="text-xs text-gray-400">
        نظام تحليلات أول‑طرف مدمج — الأرقام مقيسة مباشرة من زوّار موقعك. صفحة «الزوّار المباشرون» في القائمة لعرض من يتصفّح الآن لحظيًا. الجغرافيا على مستوى المدينة والـreal-time المتقدّم وسلوك التنقّل تُكمّلها Vercel/GA4.
    </div>
</x-filament-panels::page>
