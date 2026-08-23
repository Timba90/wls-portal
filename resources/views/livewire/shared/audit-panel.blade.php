<div>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        Vollständige Änderungshistorie. Einträge sind unveränderlich und können nicht gelöscht werden.
    </p>

    <div class="divide-y divide-gray-200 dark:divide-dark-600">
        @forelse ($entries as $entry)
            <div class="py-3" wire:key="audit-{{ $entry->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge :color="$entry->event->color()" :text="$entry->event->label()" sm />

                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $entry->created_at?->format('d.m.Y H:i') }}
                        · {{ $entry->user?->name ?? 'System' }}
                        @if ($entry->ip_address)
                            · {{ $entry->ip_address }}
                        @endif
                    </span>
                </div>

                @if ($entry->description)
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $entry->description }}</p>
                @endif

                @php($changes = $entry->changes())

                @if ($changes !== [])
                    <div class="mt-2 space-y-1 text-sm">
                        @foreach ($changes as $feld => $werte)
                            <div class="grid grid-cols-3 gap-2" wire:key="audit-{{ $entry->id }}-{{ $feld }}">
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ $labels[$feld] ?? $feld }}
                                </span>
                                <span class="truncate text-gray-500 line-through dark:text-gray-400">
                                    {{ \App\Support\AuditValueFormatter::format($werte['alt']) }}
                                </span>
                                <span class="truncate text-gray-800 dark:text-gray-100">
                                    {{ \App\Support\AuditValueFormatter::format($werte['neu']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Noch keine Änderungen protokolliert.
            </p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $entries->links() }}
    </div>
</div>
