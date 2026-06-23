<x-filament-panels::page>
    <div class="space-y-3">
        @foreach($this->checks as $key => $check)
            @php
                $colors = [
                    'ok'      => 'bg-success-50 border-success-200 text-success-700 dark:bg-success-950/40 dark:border-success-800 dark:text-success-400',
                    'warning' => 'bg-warning-50 border-warning-200 text-warning-700 dark:bg-warning-950/40 dark:border-warning-800 dark:text-warning-400',
                    'error'   => 'bg-danger-50 border-danger-200 text-danger-700 dark:bg-danger-950/40 dark:border-danger-800 dark:text-danger-400',
                ];
                $icons  = ['ok' => '✓', 'warning' => '⚠', 'error' => '✗'];
                $btn = [
                    'primary' => 'bg-primary-600 hover:bg-primary-500 text-white',
                    'success' => 'bg-success-600 hover:bg-success-500 text-white',
                    'warning' => 'bg-warning-500 hover:bg-warning-400 text-white',
                    'danger'  => 'bg-danger-600 hover:bg-danger-500 text-white',
                    'gray'    => 'bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200',
                ];
                $color = $colors[$check['status']] ?? $colors['error'];
                $icon  = $icons[$check['status']] ?? '?';
                $btnClass = $btn[$check['actionColor'] ?? 'primary'] ?? $btn['primary'];
            @endphp

            <div class="flex items-center gap-4 rounded-xl border p-4 {{ $color }}">
                <span class="text-xl font-bold w-6 text-center shrink-0">{{ $icon }}</span>

                <div class="flex-1 min-w-0">
                    <div class="font-semibold">{{ $check['label'] ?? $key }}</div>
                    <div class="text-sm opacity-80">{{ $check['message'] }}</div>
                </div>

                @if(!empty($check['action']))
                    <button
                        type="button"
                        wire:click="{{ $check['action'] }}"
                        wire:loading.attr="disabled"
                        wire:target="{{ $check['action'] }}"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition disabled:opacity-50 {{ $btnClass }}"
                    >
                        <svg wire:loading wire:target="{{ $check['action'] }}" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="{{ $check['action'] }}">{{ $check['actionLabel'] ?? 'تنفيذ' }}</span>
                        <span wire:loading wire:target="{{ $check['action'] }}">جارٍ...</span>
                    </button>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4 text-xs text-gray-400">
        مركز الصيانة — اضغط أي زر لتنفيذ الإجراء مباشرة. بعض الإجراءات (التحديثات، مسح الكاش) تؤثر على الموقع فوراً.
    </div>
</x-filament-panels::page>
