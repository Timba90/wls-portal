@props(['components'])

{{-- Anzeige von Leistungsbestandteilen. --}}
@forelse ($components as $component)
    <div class="flex items-start justify-between gap-4 border-b border-line py-2 last:border-0">
        <div>
            <p class="text-sm font-medium text-ink">{{ $component->title }}</p>

            @if ($component->description)
                <p class="text-sm text-ink-muted">{{ $component->description }}</p>
            @endif
        </div>

        @if ($component->purchasePrice() || $component->salesPrice())
            <p class="shrink-0 text-sm tabular-nums text-ink-base">
                @if ($component->purchasePrice())
                    EK {{ $component->purchasePrice()->format() }}
                @endif
                @if ($component->salesPrice())
                    VK {{ $component->salesPrice()->format() }}
                @endif
            </p>
        @endif
    </div>
@empty
    <p class="py-4 text-center text-sm text-ink-muted">
        Keine Leistungsbestandteile hinterlegt.
    </p>
@endforelse
