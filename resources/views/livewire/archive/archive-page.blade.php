<div>
    <x-page title="Archiv" subtitle="Archivierte Datensätze erscheinen nicht in der globalen Suche. Hier lassen sie sich gezielt finden.">

        <x-card>
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    @foreach ([
                        'kunden' => 'Kunden',
                        'ansprechpartner' => 'Ansprechpartner',
                        'artikel' => 'Artikel / Leistungen',
                        'leistungen' => 'Kundenleistungen',
                    ] as $key => $label)
                        <x-button sm
                                  :color="$tab === $key ? 'primary' : 'secondary'"
                                  :outline="$tab !== $key"
                                  wire:click="$set('tab', '{{ $key }}')"
                                  wire:key="tab-{{ $key }}">
                            {{ $label }} ({{ $counts[$key] }})
                        </x-button>
                    @endforeach
                </div>

                <div class="sm:w-72">
                    <x-input wire:model.live.debounce.300ms="search"
                             placeholder="Im Archiv suchen"
                             icon="magnifying-glass" />
                </div>
            </div>

            <div class="divide-y divide-line">
                @forelse ($records as $record)
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between"
                         wire:key="archiv-{{ $tab }}-{{ $record->id }}">
                        @switch ($tab)
                            @case ('ansprechpartner')
                                <div>
                                    <a href="{{ route('contacts.show', $record) }}"
                                       wire:navigate
                                       class="font-medium text-accent hover:underline">
                                        {{ $record->fullName() }}
                                    </a>
                                    <p class="text-sm text-ink-muted">
                                        {{ $record->assignments->map->customer->filter()->map->short_label->implode(', ') ?: 'Keine Kundenzuordnung' }}
                                    </p>
                                </div>
                                @break

                            @case ('artikel')
                                <div>
                                    <a href="{{ route('products.show', $record) }}"
                                       wire:navigate
                                       class="font-medium text-accent hover:underline">
                                        {{ $record->name }}
                                    </a>
                                    <p class="text-sm text-ink-muted">{{ $record->internal_name }}</p>
                                </div>
                                @break

                            @case ('leistungen')
                                <div>
                                    <a href="{{ route('customer-services.show', [$record->customer, $record]) }}"
                                       wire:navigate
                                       class="font-medium text-accent hover:underline">
                                        {{ $record->name }}
                                    </a>
                                    <p class="text-sm text-ink-muted">
                                        {{ $record->customer->customer_number }} · {{ $record->customer->displayName() }}
                                        · {{ $record->salesPrice()->format() }}
                                    </p>
                                </div>
                                @break

                            @default
                                <div>
                                    <a href="{{ route('customers.show', $record) }}"
                                       wire:navigate
                                       class="font-medium text-accent hover:underline">
                                        {{ $record->displayName() }}
                                    </a>
                                    <p class="text-sm text-ink-muted">
                                        {{ $record->customer_number }} · {{ $record->short_label }}
                                    </p>
                                </div>
                        @endswitch

                        <span class="shrink-0 text-sm text-ink-muted">
                            archiviert am {{ $record->archived_at?->format('d.m.Y') ?? '—' }}
                        </span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-muted">
                        Im Archiv wurde nichts gefunden.
                    </p>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $records->links() }}
            </div>
        </x-card>
    </x-page>
</div>
