@props(['title' => null, 'subtitle' => null, 'padded' => true])

{{-- Flächenkarte des Entwurfs: #171C21 auf #242B31, Radius 10. --}}
<section {{ $attributes->class(['rounded-[10px] border border-line bg-panel']) }}>
    @if ($title || isset($header))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-[12.5px] font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-[11px] text-ink-muted">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($header)
                <div class="flex flex-wrap items-center gap-2">{{ $header }}</div>
            @endisset
        </div>
    @endif

    <div @class(['px-4 py-4' => $padded])>
        {{ $slot }}
    </div>
</section>
