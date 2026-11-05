<div>
    <x-page title="Tickets" subtitle="Ihre Anliegen und unsere Antworten.">
        <x-slot:actions>
            <x-button icon="plus" :href="route('customer-portal.tickets.create')" wire:navigate>Anliegen melden</x-button>
        </x-slot:actions>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <x-kpi-tile label="Offen" :value="$openCount" note="Wir kümmern uns darum" />
            <x-kpi-tile label="Rückfrage an Sie" :value="$waitingCount" note="Wir warten auf Ihre Antwort" />
            <x-kpi-tile label="Gesamt" :value="$totalCount" note="Alle Tickets Ihres Kundenkontos" />
        </div>

        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="sm:w-72">
                <x-input wire:model.live.debounce.300ms="search" label="Suche" placeholder="Betreff oder Ticketnummer" icon="magnifying-glass" />
            </div>

            <div class="flex gap-1 rounded-[9px] border border-line bg-panel p-1">
                @foreach (['offen' => 'Offen', 'erledigt' => 'Erledigt', 'alle' => 'Alle'] as $schluessel => $beschriftung)
                    <button type="button"
                            wire:click="$set('view', '{{ $schluessel }}')"
                            @class([
                                'whitespace-nowrap rounded-[6px] px-3 py-1.5 text-[12px] font-medium transition',
                                'bg-accent text-accent-ink' => $view === $schluessel,
                                'text-ink-muted hover:bg-raised hover:text-ink-base' => $view !== $schluessel,
                            ])>{{ $beschriftung }}</button>
                @endforeach
            </div>
        </div>

        <x-panel :padded="false">
            <div class="flex flex-col divide-y divide-line">
                @forelse ($tickets as $ticket)
                    <a href="{{ route('customer-portal.tickets.show', $ticket) }}"
                       wire:navigate
                       wire:key="ticket-{{ $ticket->id }}"
                       class="flex flex-col gap-2 px-4 py-3 transition hover:bg-raised/40 sm:flex-row sm:items-center sm:justify-between">
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-[12.5px] font-medium text-ink">{{ $ticket->subject }}</span>
                            <span class="font-mono text-[10.5px] text-ink-faint">
                                {{ $ticket->ticket_number }}
                                @if ($ticket->project) · {{ $ticket->project->name }} @endif
                                · {{ $ticket->public_messages_count }} {{ $ticket->public_messages_count === 1 ? 'Nachricht' : 'Nachrichten' }}
                                · {{ $ticket->last_activity_at?->diffForHumans() }}
                            </span>
                        </span>

                        <span class="flex flex-none items-center gap-2">
                            @if ($ticket->priority->weight() >= 3)
                                <x-status-pill :kind="$ticket->priority->pillKind()" :label="$ticket->priority->label()" :dot="false" />
                            @endif
                            <x-status-pill :kind="$ticket->status->pillKind()" :label="$ticket->status->label()" />
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-[12.5px] text-ink-muted">Keine Tickets in dieser Ansicht.</p>
                @endforelse
            </div>
        </x-panel>

        <div class="mt-3">{{ $tickets->links() }}</div>
    </x-page>
</div>
