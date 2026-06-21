<x-filament-panels::page>
    <div class="space-y-4">
        @foreach($this->checks as $key => $check)
            @php
                $labels = [
                    'database'   => 'قاعدة البيانات',
                    'storage'    => 'التخزين',
                    'r2'         => 'Cloudflare R2',
                    'frontend'   => 'الموقع الأمامي',
                    'api'        => 'الـ API',
                    'wallpapers' => 'الخلفيات',
                    'categories' => 'الأقسام',
                ];
                $colors = [
                    'ok'      => 'bg-success-50 border-success-200 text-success-700 dark:bg-success-950 dark:border-success-800 dark:text-success-400',
                    'warning' => 'bg-warning-50 border-warning-200 text-warning-700 dark:bg-warning-950 dark:border-warning-800 dark:text-warning-400',
                    'error'   => 'bg-danger-50 border-danger-200 text-danger-700 dark:bg-danger-950 dark:border-danger-800 dark:text-danger-400',
                ];
                $icons = [
                    'ok'      => '✓',
                    'warning' => '⚠',
                    'error'   => '✗',
                ];
                $color = $colors[$check['status']] ?? $colors['error'];
                $icon  = $icons[$check['status']] ?? '?';
            @endphp
            <div class="flex items-center gap-4 rounded-lg border p-4 {{ $color }}">
                <span class="text-xl font-bold w-6 text-center">{{ $icon }}</span>
                <div>
                    <div class="font-semibold">{{ $labels[$key] ?? $key }}</div>
                    <div class="text-sm opacity-80">{{ $check['message'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
