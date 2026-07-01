<x-filament-panels::page>
    @php
        $p = $this->ops['pulse'] ?? [];
        $online = $p['online'] ?? ['total' => 0, 'members' => 0, 'guests' => 0];
        $eventMap = [
            'login_no_2fa'    => ['✅ دخول', '#16a34a'],
            'otp_success'     => ['✅ دخول (2FA)', '#16a34a'],
            'otp_sent'        => ['📤 إرسال رمز', '#8a95ad'],
            'recovery_used'   => ['🔑 دخول احتياطي', '#d97706'],
            'password_failed' => ['❌ كلمة مرور خاطئة', '#dc2626'],
            'otp_failed'      => ['⚠️ رمز خاطئ', '#d97706'],
            'otp_locked'      => ['🔒 قُفل بعد ٣ محاولات', '#dc2626'],
            'recovery_failed' => ['⚠️ رمز احتياطي خاطئ', '#d97706'],
        ];
    @endphp

    <style>
        .ops-grid { display:grid; grid-template-columns:repeat(12,1fr); gap:12px; align-items:stretch; }
        .ops-tile { border-radius:16px; padding:14px 16px; border:1px solid #e5e7eb; background:#fff; display:flex; flex-direction:column; }
        .dark .ops-tile { border-color:rgba(255,255,255,.08); background:rgba(255,255,255,.03); }
        .ops-head { grid-column:span 12; font-size:12px; font-weight:800; color:#6b7280; margin:6px 2px -4px; }
        .dark .ops-head { color:#9aa6bd; }
        .ops-s3{grid-column:span 3;} .ops-s4{grid-column:span 4;} .ops-s6{grid-column:span 6;} .ops-s12{grid-column:span 12;}
        @media (max-width:1023px){ .ops-grid{grid-template-columns:repeat(6,1fr);} .ops-s3{grid-column:span 3;} .ops-s4{grid-column:span 6;} .ops-s6{grid-column:span 6;} }
        @media (max-width:639px){ .ops-grid{grid-template-columns:repeat(2,1fr);} .ops-s3,.ops-s4,.ops-s6,.ops-s12{grid-column:span 2;} }
        .ops-kpi { font-size:34px; font-weight:900; line-height:1; }
        .ops-cap { font-size:11px; color:#6b7280; margin-top:6px; } .dark .ops-cap{color:#9aa6bd;}
        .ops-task { text-decoration:none; transition:transform .12s; }
        .ops-task:hover { transform:translateY(-2px); }
        .ops-launch { display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:10px; margin-top:10px; }
        .ops-app { display:flex; flex-direction:column; align-items:center; gap:5px; padding:12px 6px; border-radius:14px;
                   border:1px solid #e5e7eb; text-decoration:none; color:#374151; transition:all .12s; }
        .dark .ops-app { border-color:rgba(255,255,255,.08); color:#e5e7eb; }
        .ops-app:hover { border-color:#3b82f6; background:rgba(59,130,246,.08); transform:translateY(-2px); }
        .ops-app .em { font-size:23px; } .ops-app .lb { font-size:11px; font-weight:600; text-align:center; }
        .ops-row { display:flex; align-items:center; gap:8px; font-size:13px; padding:6px 0; border-bottom:1px solid #f0f0f0; }
        .dark .ops-row { border-color:rgba(255,255,255,.05); }
        .ops-row:last-child { border-bottom:0; }
        .ops-dot { height:9px; width:9px; border-radius:99px; flex-shrink:0; }
    </style>

    <div class="ops-grid">
        {{-- ── Live pulse (KPIs) ── --}}
        <div class="ops-tile ops-s3" style="border-color:#16a34a33; background:#16a34a0f;">
            <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#16a34a;font-weight:700;">
                <span style="position:relative;display:inline-flex;height:9px;width:9px;">
                    <span style="position:absolute;height:100%;width:100%;border-radius:99px;background:#22c55e;opacity:.75;animation:ping 1.2s cubic-bezier(0,0,.2,1) infinite;"></span>
                    <span style="position:relative;height:9px;width:9px;border-radius:99px;background:#16a34a;"></span>
                </span> متواجدون الآن
            </div>
            <div class="ops-kpi" style="color:#16a34a;margin-top:6px;">{{ number_format($online['total']) }}</div>
            <div class="ops-cap">👤 {{ $online['members'] }} · 🌐 {{ $online['guests'] }}</div>
        </div>

        <div class="ops-tile ops-s3">
            <div class="ops-kpi">{{ number_format($p['visitors_today'] ?? 0) }}</div>
            <div class="ops-cap">👁️ زوّار اليوم</div>
        </div>
        <div class="ops-tile ops-s3">
            <div class="ops-kpi">{{ number_format($p['registrations_today'] ?? 0) }}</div>
            <div class="ops-cap">🆕 تسجيلات اليوم</div>
        </div>

        <div class="ops-tile ops-s3">
            <div class="ops-cap" style="margin:0 0 6px;font-weight:700;">نبض النظام</div>
            @foreach($p['system'] ?? [] as $s)
                <div style="display:flex;align-items:center;gap:6px;font-size:11.5px;padding:2px 0;">
                    <span class="ops-dot" style="background:{{ $s['ok'] ? '#22c55e' : '#ef4444' }};"></span>
                    <span style="opacity:.85;">{{ $s['label'] }}</span>
                    <span style="margin-inline-start:auto;opacity:.55;">{{ $s['note'] }}</span>
                </div>
            @endforeach
        </div>

        {{-- ── Task queue ── --}}
        <div class="ops-head">🚨 يحتاج إجراءك</div>
        @foreach($this->ops['tasks'] ?? [] as $t)
            @php $has = ($t['count'] ?? 0) > 0; @endphp
            <a href="{{ $t['url'] }}" class="ops-tile ops-task ops-s3"
               style="{{ $has ? 'border-color:#dc262655;background:#dc26260f;' : '' }}">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:22px;">{{ $t['icon'] }}</span>
                    <span style="font-size:32px;font-weight:900;color:{{ $has ? '#dc2626' : '#cbd5e1' }};">{{ number_format($t['count']) }}</span>
                </div>
                <div style="font-size:12px;margin-top:2px;color:{{ $has ? '#dc2626' : '#6b7280' }};font-weight:{{ $has ? '700' : '400' }};">{{ $t['label'] }}</div>
                <div style="font-size:11px;margin-top:2px;color:{{ $has ? '#dc2626' : '#9ca3af' }};">{{ $has ? 'اضغط للمعالجة ←' : 'لا شيء ✓' }}</div>
            </a>
        @endforeach

        {{-- ── Quick launch ── --}}
        <div class="ops-head">⚡ دخول سريع</div>
        <div class="ops-tile ops-s12" style="padding-top:6px;">
            <div class="ops-launch">
                @foreach($this->ops['launch'] ?? [] as $l)
                    <a href="{{ $l['url'] }}" class="ops-app">
                        <span class="em">{{ $l['icon'] }}</span>
                        <span class="lb">{{ $l['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ── Activity + Security ── --}}
        <div class="ops-tile {{ $this->isSuper ? 'ops-s6' : 'ops-s12' }}">
            <div class="ops-cap" style="margin:0 0 6px;font-weight:800;font-size:13px;">🕒 آخر النشاط</div>
            @forelse($this->ops['activity'] ?? [] as $a)
                <div class="ops-row">
                    <span>{{ $a['icon'] }}</span>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $a['text'] }}</span>
                    <span style="font-size:11px;opacity:.55;flex-shrink:0;">{{ $a['at']?->diffForHumans() }}</span>
                </div>
            @empty
                <div class="ops-cap">لا يوجد نشاط بعد.</div>
            @endforelse
        </div>

        @if($this->isSuper)
            <div class="ops-tile ops-s6">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span class="ops-cap" style="margin:0;font-weight:800;font-size:13px;">🔐 نبض الأمان — آخر الدخول</span>
                    @if(($this->ops['failed_logins'] ?? 0) > 0)
                        <span style="font-size:11px;padding:2px 8px;border-radius:99px;background:#dc26261f;color:#dc2626;font-weight:700;">{{ $this->ops['failed_logins'] }} فاشلة (٢٤س)</span>
                    @endif
                </div>
                @forelse($this->ops['logins'] ?? [] as $log)
                    @php $meta = $eventMap[$log['event']] ?? [$log['event'], '#8a95ad']; @endphp
                    <div class="ops-row" style="font-size:12px;">
                        <span style="color:{{ $meta[1] }};font-weight:600;width:120px;flex-shrink:0;">{{ $meta[0] }}</span>
                        <span style="opacity:.7;">{{ \App\Services\AnalyticsService::flag($log['country'] ?? null) }} {{ $log['device'] ?? '' }} · {{ $log['browser'] ?? '' }}</span>
                        <span style="margin-inline-start:auto;font-size:11px;opacity:.5;flex-shrink:0;">{{ \Illuminate\Support\Carbon::parse($log['created_at'])->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="ops-cap">لا توجد سجلات دخول بعد.</div>
                @endforelse
            </div>
        @endif
    </div>

    <style>@keyframes ping{75%,100%{transform:scale(2);opacity:0;}}</style>
</x-filament-panels::page>
