<div class="relative w-full" x-data x-on:click.outside="$wire.close()">
    <x-input wire:model.live.debounce.300ms="term"
             placeholder="Kunden, Ansprechpartner, Artikel oder Leistungen suchen"
             icon="magnifying-glass"
             autocomplete="off" />

    @if ($showResults)
        <div class="absolute left-0 right-0 top-full z-40 mt-1 max-h-96 overflow-y-auto rounded-md border border-line bg-white shadow-lg">
            @forelse ($groups as $group)
                <div wire:key="gruppe-{{ $loop->index }}">
                    <p class="border-b border-line bg-raised px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        {{ $group['typ'] }}
                    </p>

                    @foreach ($group['treffer'] as $treffer)
                        <a href="{{ $treffer['url'] }}"
                           wire:navigate
                           wire:click="close"
                           wire:key="treffer-{{ $loop->parent->index }}-{{ $loop->index }}"
                           class="block px-3 py-2 transition hover:bg-raised">
                            <span class="block text-sm font-medium text-ink">
                                {{ $treffer['name'] }}
                            </span>
                            <span class="block text-xs text-ink-muted">
                                {{ $treffer['zusatz'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @empty
                <p class="px-3 py-4 text-center text-sm text-ink-muted">
                    Keine Treffer. Archivierte Datensätze sind ausgeschlossen.
                </p>
            @endforelse
        </div>
    @endif
</div>
