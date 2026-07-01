<x-filament-panels::page>
    @php
        $p = $this->ops['pulse'] ?? [];
        $online = $p['online'] ?? ['total' => 0, 'members' => 0, 'guests' => 0];
        $eventMap = [
            'login_no_2fa'   => ['✅ دخول', 'text-success-600 dark:text-success-400'],
            'otp_success'    => ['✅ دخول (2FA)', 'text-success-600 dark:text-success-400'],
            'otp_sent'       => ['📤 إرسال رمز', 'text-gray-500'],
            'recovery_used'  => ['🔑 دخول احتياطي', 'text-warning-600 dark:text-warning-400'],
            'password_failed'=> ['❌ كلمة مرور خاطئة', 'text-danger-600 dark:text-danger-400'],
            'otp_failed'     => ['⚠️ رمز خاطئ', 'text-warning-600 dark:text-warning-400'],
            'otp_locked'     => ['🔒 قُفل بعد ٣ محاولات', 'text-danger-600 dark:text-danger-400'],
            'recovery_failed'=> ['⚠️ رمز احتياطي خاطئ', 'text-warning-600 dark:text-warning-400'],
        ];
    @endphp

    {{-- ═══ 1) Live pulse + system health ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <div class="rounded-2xl border border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-950/30 p-4">
            <div class="flex items-center gap-1.5 text-xs text-success-700 dark:text-success-400">
                <span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success-500"></span></span>
                متواجدون الآن
            </div>
            <div class="text-4xl font-extrabold text-success-700 dark:text-success-400 mt-1">{{ number_format($online['total']) }}</div>
            <div class="text-xs text-gray-500 mt-1">👤 أعضاء: {{ $online['members'] }} · 🌐 زوّار: {{ $online['guests'] }}</div>
        </div>

        <div class="rounded-2xl border border-gray-200 dark:border-white/10 p-4 grid grid-cols-2 gap-3">
            <div><div class="text-2xl font-extrabold">{{ number_format($p['visitors_today'] ?? 0) }}</div><div class="text-xs text-gray-500">👁️ زوّار اليوم</div></div>
            <div><div class="text-2xl font-extrabold">{{ number_format($p['registrations_today'] ?? 0) }}</div><div class="text-xs text-gray-500">🆕 تسجيلات اليوم</div></div>
        </div>

        <div class="rounded-2xl border border-gray-200 dark:border-white/10 p-4">
            <div class="text-xs text-gray-500 mb-2 font-semibold">نبض النظام</div>
            <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                @foreach($p['system'] ?? [] as $s)
                    <div class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $s['ok'] ? 'bg-success-500' : 'bg-danger-500' }}"></span>
                        <span class="text-gray-600 dark:text-gray-300">{{ $s['label'] }}</span>
                        <span class="text-gray-400 ms-auto">{{ $s['note'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ═══ 2) Urgent task queue ═══ --}}
    <div>
        <h2 class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-2">🚨 يحتاج إجراءك</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach($this->ops['tasks'] ?? [] as $t)
                @php $has = ($t['count'] ?? 0) > 0; @endphp
                <a href="{{ $t['url'] }}"
                   class="rounded-2xl border p-4 transition hover:scale-[1.02] {{ $has ? 'border-danger-300 dark:border-danger-800 bg-danger-50 dark:bg-danger-950/30' : 'border-gray-200 dark:border-white/10 bg-white dark:bg-white/5' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-2xl">{{ $t['icon'] }}</span>
                        <span class="text-3xl font-extrabold {{ $has ? 'text-danger-600 dark:text-danger-400' : 'text-gray-300 dark:text-gray-600' }}">{{ number_format($t['count']) }}</span>
                    </div>
                    <div class="text-xs mt-1 {{ $has ? 'text-danger-700 dark:text-danger-300 font-semibold' : 'text-gray-500' }}">{{ $t['label'] }}</div>
                    <div class="text-[11px] mt-1 {{ $has ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">{{ $has ? 'اضغط للمعالجة ←' : 'لا شيء ✓' }}</div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ═══ 3) Quick launch ═══ --}}
    <div>
        <h2 class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-2">⚡ دخول سريع</h2>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach($this->ops['launch'] ?? [] as $l)
                <a href="{{ $l['url'] }}"
                   class="group flex flex-col items-center justify-center gap-1.5 rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 hover:bg-primary-50 dark:hover:bg-primary-950/30 hover:border-primary-300 dark:hover:border-primary-700 p-4 transition text-center">
                    <span class="text-2xl group-hover:scale-110 transition-transform">{{ $l['icon'] }}</span>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ $l['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ═══ 4/5) Activity feed + Security pulse ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div class="rounded-2xl border border-gray-200 dark:border-white/10 p-4">
            <h2 class="text-sm font-bold mb-3">🕒 آخر النشاط</h2>
            <div class="space-y-2">
                @forelse($this->ops['activity'] ?? [] as $a)
                    <div class="flex items-center gap-2 text-sm border-b border-gray-100 dark:border-white/5 pb-2 last:border-0">
                        <span>{{ $a['icon'] }}</span>
                        <span class="flex-1 truncate text-gray-700 dark:text-gray-200">{{ $a['text'] }}</span>
                        <span class="text-xs text-gray-400 shrink-0">{{ $a['at']?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">لا يوجد نشاط بعد.</p>
                @endforelse
            </div>
        </div>

        @if($this->isSuper)
            <div class="rounded-2xl border border-gray-200 dark:border-white/10 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-bold">🔐 نبض الأمان — آخر عمليات الدخول</h2>
                    @if(($this->ops['failed_logins'] ?? 0) > 0)
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-danger-500/15 text-danger-600 dark:text-danger-400 font-semibold">{{ $this->ops['failed_logins'] }} فاشلة (٢٤ساعة)</span>
                    @endif
                </div>
                <div class="space-y-1.5">
                    @forelse($this->ops['logins'] ?? [] as $log)
                        @php $meta = $eventMap[$log['event']] ?? [$log['event'], 'text-gray-500']; @endphp
                        <div class="flex items-center gap-2 text-xs border-b border-gray-100 dark:border-white/5 pb-1.5 last:border-0">
                            <span class="{{ $meta[1] }} font-medium w-32 shrink-0">{{ $meta[0] }}</span>
                            <span class="text-gray-500">{{ \App\Services\AnalyticsService::flag($log['country'] ?? null) }} {{ $log['device'] ?? '' }} · {{ $log['browser'] ?? '' }}</span>
                            <span class="text-gray-400 ms-auto shrink-0">{{ \Illuminate\Support\Carbon::parse($log['created_at'])->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">لا توجد سجلات دخول بعد.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
