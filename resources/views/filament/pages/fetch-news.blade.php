<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
            <p class="font-semibold text-gray-800 dark:text-gray-100 mb-1">كيف تشتغل؟</p>
            <p>١) أضف مواقعك من قسم <span class="font-semibold">«مصادر الأخبار»</span>.</p>
            <p>٢) اضغط <span class="font-semibold">«جلب آخر الأخبار»</span> فوق — تطلع لك آخر الأخبار من مصادرك.</p>
            <p>٣) أشّر اللي يعجبك، واضغط <span class="font-semibold">«توليد المقالات المحددة»</span> — الذكاء يلخّص ويترجم ويملأ كل شي ويحفظه <span class="font-semibold">كمسودة</span>.</p>
            <p>٤) راجع المسودات من قسم <span class="font-semibold">«الأخبار»</span>، اضبط الصورة، ثم انشر.</p>
        </div>

        @if (empty($this->items))
            <div class="text-center py-16 text-gray-400 border border-dashed border-gray-300 dark:border-white/10 rounded-2xl">
                اضغط «جلب آخر الأخبار» لعرض آخر العناوين من مصادرك.
            </div>
        @else
            <form>
                {{ $this->form }}
            </form>
        @endif
    </div>
</x-filament-panels::page>
