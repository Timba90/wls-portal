<div class="relative w-full" x-data x-on:click.outside="$wire.close()">
    <x-input wire:model.live.debounce.300ms="term"
             placeholder="Kunden, Ansprechpartner, Artikel oder Leistungen suchen"
             icon="magnifying-glass"
             autocomplete="off" />

    @if ($showResults)
        <div class="absolute left-0 right-0 top-full z-40 mt-1 max-h-96 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-dark-600 dark:bg-dark-700">
            @forelse ($groups as $group)
                <div wire:key="gruppe-{{ $loop->index }}">
                    <p class="border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-dark-600 dark:bg-dark-800 dark:text-gray-400">
                        {{ $group['typ'] }}
                    </p>

                    @foreach ($group['treffer'] as $treffer)
                        <a href="{{ $treffer['url'] }}"
                           wire:navigate
                           wire:click="close"
                           wire:key="treffer-{{ $loop->parent->index }}-{{ $loop->index }}"
                           class="block px-3 py-2 transition hover:bg-gray-50 dark:hover:bg-dark-600">
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ $treffer['name'] }}
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                {{ $treffer['zusatz'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @empty
                <p class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                    Keine Treffer. Archivierte Datensätze sind ausgeschlossen.
                </p>
            @endforelse
        </div>
    @endif
</div>
