@props(['components'])

{{-- Anzeige von Leistungsbestandteilen. --}}
@forelse ($components as $component)
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 py-2 last:border-0 dark:border-dark-600">
        <div>
            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $component->title }}</p>

            @if ($component->description)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $component->description }}</p>
            @endif
        </div>

        @if ($component->purchasePrice() || $component->salesPrice())
            <p class="shrink-0 text-sm tabular-nums text-gray-600 dark:text-gray-300">
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
    <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
        Keine Leistungsbestandteile hinterlegt.
    </p>
@endforelse
