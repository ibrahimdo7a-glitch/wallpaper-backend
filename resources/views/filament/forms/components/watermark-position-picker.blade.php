<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $statePath  = $getStatePath();
        $img        = $getImageUrl();
        $positions  = $getPositions();
        $labelsJson = \Illuminate\Support\Js::from($positions);
    @endphp

    {{-- Inline styles on purpose: Tailwind utilities in this custom view get
         purged from Filament's prebuilt CSS, so we don't rely on them. --}}
    <div x-data="{ state: $wire.$entangle('{{ $statePath }}'), labels: {{ $labelsJson }} }">
        <div style="position:relative; width:100%; aspect-ratio:16/9; border-radius:12px; overflow:hidden; border:1px solid #2a323d; background:#0b0f14;">
            @if($img)
                <img src="{{ $img }}" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;" />
                <div style="position:absolute; inset:0; background:rgba(0,0,0,0.35);"></div>
            @endif

            <div style="position:absolute; inset:0; display:grid; grid-template-columns:repeat(3,1fr); grid-template-rows:repeat(3,1fr);">
                @foreach($positions as $value => $label)
                    <button
                        type="button"
                        @click="state = '{{ $value }}'"
                        title="{{ $label }}"
                        style="border:none; padding:0; margin:0; cursor:pointer; display:flex; align-items:center; justify-content:center; background:transparent; transition:background .15s;"
                        :style="state === '{{ $value }}'
                            ? 'background:rgba(59,130,246,0.35); box-shadow: inset 0 0 0 2px #3b82f6;'
                            : 'background:transparent;'"
                    >
                        <span
                            style="display:block; border-radius:9999px; transition:all .15s;"
                            :style="state === '{{ $value }}'
                                ? 'width:18px; height:18px; background:#60a5fa; box-shadow:0 0 0 3px rgba(255,255,255,0.75);'
                                : 'width:11px; height:11px; background:rgba(255,255,255,0.45);'"
                        ></span>
                    </button>
                @endforeach
            </div>
        </div>

        <p style="font-size:12px; color:#9aa3af; margin-top:6px;">
            المكان المختار:
            <span style="font-weight:700; color:#3b82f6;" x-text="labels[state] ?? state"></span>
            — اضغط على أي مربع لنقل التوقيع.
        </p>
    </div>
</x-dynamic-component>
