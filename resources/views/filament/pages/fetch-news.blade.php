<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
            <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">كيف تشتغل؟</p>
            <p>١) أضف مواقعك من قسم <span class="font-semibold">«مصادر الأخبار»</span>.</p>
            <p>٢) اضغط <span class="font-semibold">«جلب آخر الأخبار»</span> فوق — تطلع لك آخر العناوين.</p>
            <p>٣) <span class="font-semibold">اضغط على عنوان الخبر</span> ليفتح المقال الأصلي في نافذة جديدة وتقرأه، ثم أشّر اللي يعجبك واضغط <span class="font-semibold">«توليد المقالات المحددة»</span>.</p>
            <p>٤) راجع المسودات من قسم <span class="font-semibold">«الأخبار»</span>، اضبط الصورة، ثم انشر.</p>
        </div>

        @if (! empty($generated))
            <div class="rounded-2xl border border-success-300 dark:border-success-500/30 bg-success-50 dark:bg-success-500/10 p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold text-success-700 dark:text-success-300">✅ تم توليد {{ count($generated) }} مقال — افتحها للمراجعة والنشر</p>
                    <button type="button"
                        x-data
                        x-on:click="{{ \Illuminate\Support\Js::from(collect($generated)->pluck('url')) }}.forEach(u => window.open(u, '_blank'))"
                        class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-success-600 hover:bg-success-700 px-3 py-1.5 text-sm font-medium text-white">
                        افتح الكل ↗
                    </button>
                </div>
                <ul class="space-y-1">
                    @foreach ($generated as $g)
                        <li>
                            <a href="{{ $g['url'] }}" target="_blank" rel="noopener noreferrer"
                                class="text-sm text-success-700 dark:text-success-300 hover:underline">
                                • {{ $g['title'] }} ↗
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (empty($items))
            <div class="text-center py-16 text-gray-400 border border-dashed border-gray-300 dark:border-white/10 rounded-2xl">
                @if (empty($generated))
                    اضغط «جلب آخر الأخبار» لعرض آخر العناوين من مصادرك.
                @else
                    خلصت الأخبار المحددة. اضغط «جلب آخر الأخبار» لجلب المزيد.
                @endif
            </div>
        @else
            <div class="flex items-center justify-between">
                <button type="button" wire:click="toggleAll"
                    class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                    {{ count($selected) >= count($items) ? 'إلغاء التحديد' : 'تحديد الكل' }}
                </button>
                <span class="text-sm text-gray-500">
                    محدّد: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ count($selected) }}</span> من {{ count($items) }}
                </span>
            </div>

            <ul class="divide-y divide-gray-100 dark:divide-white/5 rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden">
                @foreach ($items as $item)
                    <li class="flex items-start gap-3 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        <input type="checkbox" wire:model.live="selected" value="{{ $item['link'] }}"
                            class="mt-1 h-5 w-5 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">

                        <div class="min-w-0 flex-1">
                            <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer"
                                class="font-medium text-gray-900 dark:text-gray-100 hover:text-primary-600 dark:hover:text-primary-400 hover:underline">
                                {{ $item['title'] }}<span class="opacity-40 text-xs"> ↗</span>
                            </a>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                📰 {{ $item['source_name'] ?? '' }}@if(!empty($item['when'])) · {{ $item['when'] }}@endif
                            </div>
                        </div>

                        <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer"
                            class="shrink-0 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline whitespace-nowrap">
                            فتح ↗
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
