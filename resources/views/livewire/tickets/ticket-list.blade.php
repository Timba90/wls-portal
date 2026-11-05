<div>
    <x-page title="Tickets" subtitle="Anfragen aus dem Kundenportal, intern erfasst oder per Schnittstelle.">
        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-tile label="Offen" :value="$counts['open']" note="Neu oder vom Kunden beantwortet" />
            <x-kpi-tile label="In Bearbeitung" :value="$counts['inProgress']" note="Jemand ist dran" />
            <x-kpi-tile label="Wartet auf Kunde" :value="$counts['waiting']" note="Rückfrage gestellt" />
            <x-kpi-tile label="Nicht zugewiesen" :value="$counts['unassigned']" note="Offene Tickets ohne Zuständigkeit" />
        </div>

        <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="lg:w-72">
                <x-input wire:model.live.debounce.300ms="search" label="Suche" placeholder="Betreff, Ticketnummer, Kunde" icon="magnifying-glass" />
            </div>
            <div class="lg:w-56">
                <x-select.styled wire:model.live="customerId" label="Kunde" placeholder="Alle" :options="$customers" select="label:label|value:value" searchable />
            </div>
            <div class="lg:w-56">
                <x-select.styled wire:model.live="assignedUserId" label="Zuständig" placeholder="Alle" :options="$users" select="label:label|value:value" />
            </div>
        </div>

        <div class="mb-3 flex gap-1 overflow-x-auto rounded-[9px] border border-line bg-panel p-1 sm:w-fit">
            @foreach (['am-zug' => 'Wir sind am Zug', 'wartet' => 'Wartet auf Kunde', 'offen' => 'Alle offenen', 'erledigt' => 'Erledigt', 'alle' => 'Alle'] as $schluessel => $beschriftung)
                <button type="button"
                        wire:click="$set('view', '{{ $schluessel }}')"
                        @class([
                            'whitespace-nowrap rounded-[6px] px-3 py-1.5 text-[12px] font-medium transition',
                            'bg-accent text-accent-ink' => $view === $schluessel,
                            'text-ink-muted hover:bg-raised hover:text-ink-base' => $view !== $schluessel,
                        ])>{{ $beschriftung }}</button>
            @endforeach
        </div>

        <x-panel :padded="false">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-[12.5px]">
                    <thead>
                        <tr class="border-b border-line text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">
                            <th class="px-4 py-2.5">Ticket</th>
                            <th class="px-4 py-2.5">Kunde</th>
                            <th class="px-4 py-2.5">Priorität</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Zuständig</th>
                            <th class="px-4 py-2.5">Aktivität</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($tickets as $ticket)
                            <tr wire:key="ticket-{{ $ticket->id }}" class="transition hover:bg-raised/40">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('tickets.show', $ticket) }}" wire:navigate class="flex flex-col">
                                        <span class="truncate text-ink">{{ $ticket->subject }}</span>
                                        <span class="font-mono text-[10.5px] text-ink-faint">
                                            {{ $ticket->ticket_number }} · {{ $ticket->source->label() }}
                                            @if ($ticket->project) · {{ $ticket->project->name }} @endif
                                        </span>
                                    </a>
                                </td>
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('customers.show', $ticket->customer) }}" wire:navigate class="text-ink-base hover:text-accent">
                                        {{ $ticket->customer->displayName() }}
                                    </a>
                                </td>
                                <td class="px-4 py-2.5"><x-status-pill :kind="$ticket->priority->pillKind()" :label="$ticket->priority->label()" :dot="false" /></td>
                                <td class="px-4 py-2.5"><x-status-pill :kind="$ticket->status->pillKind()" :label="$ticket->status->label()" /></td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $ticket->assignedUser?->name ?? '—' }}</td>
                                <td class="tabular px-4 py-2.5 text-ink-muted">{{ $ticket->last_activity_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-ink-muted">Keine Tickets in dieser Ansicht.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>

        <div class="mt-3">{{ $tickets->links() }}</div>
    </x-page>
</div>
