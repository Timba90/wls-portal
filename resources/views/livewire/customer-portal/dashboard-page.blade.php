<div>
    <x-page :title="'Willkommen, '.auth('customer')->user()->name"
            :subtitle="$customer->displayName().' · '.$customer->customer_number">
        <x-slot:actions>
            <x-button icon="plus" :href="route('customer-portal.tickets.create')" wire:navigate>Anliegen melden</x-button>
        </x-slot:actions>

        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-tile label="Offene Tickets"
                        :value="$openTicketCount"
                        note="Offen, in Bearbeitung oder mit Rückfrage"
                        :href="route('customer-portal.tickets.index')" />

            <x-kpi-tile label="Laufende Projekte"
                        :value="$openProjectCount"
                        note="Geplant, laufend oder pausiert"
                        :href="route('customer-portal.projects.index')" />

            @if (! $billing['linked'])
                <x-kpi-tile label="Offene Rechnungen" value="—" note="Abrechnung noch nicht eingerichtet" />
                <x-kpi-tile label="SEPA-Mandat" value="—" note="Abrechnung noch nicht eingerichtet" />
            @elseif (! $billing['available'])
                <x-kpi-tile label="Offene Rechnungen" value="—" note="Gerade nicht abrufbar" />
                <x-kpi-tile label="SEPA-Mandat" value="—" note="Gerade nicht abrufbar" />
            @else
                <x-kpi-tile label="Offene Rechnungen"
                            :value="$billing['openAmount']->format()"
                            :note="$billing['openCount'].' '.($billing['openCount'] === 1 ? 'Rechnung' : 'Rechnungen').' offen'"
                            :href="route('customer-portal.invoices.index')" />

                <x-kpi-tile label="SEPA-Mandat"
                            :value="$billing['hasMandate'] ? 'Erteilt' : 'Fehlt'"
                            :note="$billing['hasMandate'] ? 'Rechnungen werden abgebucht' : 'Jetzt Lastschrift einrichten'"
                            :href="route('customer-portal.sepa-mandate')" />
            @endif
        </div>

        <div class="grid items-start gap-3.5 lg:grid-cols-2">
            <x-panel title="Projekte" subtitle="Fortschritt aus den Meilensteinen">
                <x-slot:header>
                    <a href="{{ route('customer-portal.projects.index') }}" wire:navigate class="text-[11px] text-accent hover:underline">alle</a>
                </x-slot:header>

                <div class="flex flex-col divide-y divide-line">
                    @forelse ($projects as $project)
                        @php($fortschritt = $project->progressPercentage())
                        <a href="{{ route('customer-portal.projects.show', $project) }}"
                           wire:navigate
                           wire:key="projekt-{{ $project->id }}"
                           class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                            <x-project-type-icon :type="$project->projectType" size="sm" />

                            <span class="flex min-w-0 flex-1 flex-col gap-1.5">
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="truncate text-[12.5px] text-ink-base">{{ $project->name }}</span>
                                    <span class="tabular flex-none text-[11px] text-ink-muted">{{ $fortschritt === null ? '—' : $fortschritt.' %' }}</span>
                                </span>
                                <span class="h-1 overflow-hidden rounded-full bg-raised">
                                    <span class="block h-full rounded-full bg-accent" style="width: {{ $fortschritt ?? 0 }}%"></span>
                                </span>
                            </span>
                        </a>
                    @empty
                        <p class="text-[12px] text-ink-muted">Derzeit laufen keine Projekte.</p>
                    @endforelse
                </div>
            </x-panel>

            <x-panel title="Letzte Tickets">
                <x-slot:header>
                    <a href="{{ route('customer-portal.tickets.index') }}" wire:navigate class="text-[11px] text-accent hover:underline">alle</a>
                </x-slot:header>

                <div class="flex flex-col divide-y divide-line">
                    @forelse ($tickets as $ticket)
                        <a href="{{ route('customer-portal.tickets.show', $ticket) }}"
                           wire:navigate
                           wire:key="ticket-{{ $ticket->id }}"
                           class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-[12.5px] text-ink-base">{{ $ticket->subject }}</span>
                                <span class="font-mono text-[10.5px] text-ink-faint">
                                    {{ $ticket->ticket_number }} · {{ $ticket->last_activity_at?->diffForHumans() }}
                                </span>
                            </span>
                            <x-status-pill :kind="$ticket->status->pillKind()" :label="$ticket->status->label()" />
                        </a>
                    @empty
                        <p class="text-[12px] text-ink-muted">Noch keine Tickets. Über „Anliegen melden" erreichen Sie uns direkt.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>
    </x-page>
</div>
