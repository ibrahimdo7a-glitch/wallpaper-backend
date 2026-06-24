<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $statePath = $getStatePath();
        $img       = $getImageUrl();
        $positions = $getPositions();
        $labelsJson = \Illuminate\Support\Js::from($positions);
    @endphp

    <div
        x-data="{ state: $wire.$entangle('{{ $statePath }}'), labels: {{ $labelsJson }} }"
        class="space-y-2"
    >
        <div
            class="relative w-full overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 select-none"
            style="aspect-ratio: 16 / 9; background:
                @if($img) center/cover no-repeat url('{{ $img }}') @else #0b0f14 @endif;"
        >
            @if($img)
                <div class="absolute inset-0 bg-black/30"></div>
            @endif

            <div class="absolute inset-0 grid grid-cols-3 grid-rows-3 gap-px">
                @foreach($positions as $value => $label)
                    <button
                        type="button"
                        @click="state = '{{ $value }}'"
                        :class="state === '{{ $value }}'
                            ? 'bg-primary-500/40 ring-2 ring-inset ring-primary-500'
                            : 'hover:bg-white/10'"
                        class="flex items-center justify-center transition-colors"
                        title="{{ $label }}"
                    >
                        <span
                            class="block rounded-full transition-all"
                            :class="state === '{{ $value }}'
                                ? 'w-4 h-4 bg-primary-400 ring-2 ring-white/70'
                                : 'w-2.5 h-2.5 bg-white/40'"
                        ></span>
                    </button>
                @endforeach
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            المكان المختار: <span class="font-semibold text-primary-600 dark:text-primary-400" x-text="labels[state] ?? state"></span>
            — اضغط على أي مربع لنقل التوقيع.
        </p>
    </div>
</x-dynamic-component>
