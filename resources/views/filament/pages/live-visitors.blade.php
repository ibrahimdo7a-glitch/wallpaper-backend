<x-filament-panels::page>
    <div wire:poll.20s="refresh">
        <div class="flex items-center gap-3 mb-4">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-success-500"></span>
            </span>
            <span class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $count }}</span>
            <span class="text-sm text-gray-500">متواجد الآن (آخر ٣ دقائق) — يُحدّث تلقائيًا كل ٢٠ ثانية</span>
        </div>

        @if($count === 0)
            <p class="text-sm text-gray-400 py-12 text-center border border-dashed border-gray-200 dark:border-white/10 rounded-2xl">
                لا يوجد زوّار متصلون الآن. افتح الموقع في تبويب آخر وسيظهر هنا خلال ثوانٍ.
            </p>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm text-start">
                    <thead class="bg-gray-50 dark:bg-white/5 text-gray-500 text-xs">
                        <tr>
                            <th class="px-3 py-2 text-start">الدولة</th>
                            <th class="px-3 py-2 text-start">الحالة</th>
                            <th class="px-3 py-2 text-start">الجهاز</th>
                            <th class="px-3 py-2 text-start">الصفحة الحالية</th>
                            <th class="px-3 py-2 text-start">صفحات</th>
                            <th class="px-3 py-2 text-start">آخر نشاط</th>
                            <th class="px-3 py-2 text-start">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($visitors as $v)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ \App\Services\AnalyticsService::flag($v['country']) }}
                                    <span class="text-gray-500">{{ $v['country'] ?: '—' }}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if($v['member'])
                                        <span class="text-success-600 dark:text-success-400 font-medium">👤 {{ $v['member'] }}</span>
                                    @else
                                        <span class="text-gray-500">🌐 زائر</span>
                                    @endif
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full ms-1 {{ $v['returning'] ? 'bg-primary-500/15 text-primary-600 dark:text-primary-400' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400' }}">
                                        {{ $v['returning'] ? 'عائد' : 'جديد' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    {{ $v['device'] === 'mobile' ? '📱' : ($v['device'] === 'tablet' ? '📲' : '💻') }}
                                    {{ $v['os'] ?: '' }} · {{ $v['browser'] ?: '' }}
                                </td>
                                <td class="px-3 py-2 max-w-[220px] truncate text-gray-600 dark:text-gray-300" dir="ltr" title="{{ $v['last_path'] }}">{{ $v['last_path'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $v['views'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $v['last_seen'] ? \Illuminate\Support\Carbon::parse($v['last_seen'])->diffForHumans() : '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-400 font-mono text-xs" dir="ltr">{{ $this->maskIp($v['ip']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="text-xs text-gray-400 mt-3">
            خصوصية: عناوين IP مخفية افتراضيًا (اضغط «إظهار IP» لكشفها — متاح للـ super admin فقط). الدولة تُقرأ من Cloudflare؛ المدينة الدقيقة تُكمّلها GA4.
        </p>
    </div>
</x-filament-panels::page>
