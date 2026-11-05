<div>
    <x-page :title="$project->name"
            :subtitle="$project->project_number.($project->projectType ? ' · '.$project->projectType->name : '')"
            back-label="Projekte ／ zurück zur Übersicht"
            :back-url="route('customer-portal.projects.index')">
        <x-slot:actions>
            <x-status-pill :kind="$project->status->pillKind()" :label="$project->status->label()" />
            <x-button icon="plus" :href="route('customer-portal.tickets.create', ['projekt' => $project->id])" wire:navigate>
                Anliegen zu diesem Projekt
            </x-button>
        </x-slot:actions>

        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-tile label="Fortschritt"
                        :value="$stats['progress'] === null ? '—' : $stats['progress'].' %'"
                        :note="$stats['milestonesDone'].' von '.$stats['milestonesTotal'].' Meilensteinen erledigt'" />
            <x-kpi-tile label="Deadline"
                        :value="$project->deadline?->format('d.m.Y') ?? '—'"
                        :note="match (true) {
                            $stats['daysUntilDeadline'] === null => 'Kein Termin vereinbart',
                            $stats['daysUntilDeadline'] < 0 => abs($stats['daysUntilDeadline']).' Tage überschritten',
                            default => 'Noch '.$stats['daysUntilDeadline'].' Tage',
                        }" />
            <x-kpi-tile label="Laufzeit"
                        :value="$stats['daysRunning'] === null ? '—' : $stats['daysRunning'].' Tage'"
                        :note="$project->start_date ? 'Seit '.$project->start_date->format('d.m.Y') : 'Noch nicht begonnen'" />
            <x-kpi-tile label="Tickets"
                        :value="$stats['ticketsOpen'].' offen'"
                        :note="$stats['ticketsTotal'].' insgesamt zu diesem Projekt'" />
        </div>

        <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-3.5">
                <x-panel title="Meilensteine">
                    @if ($project->milestones->isEmpty())
                        <p class="text-[12.5px] text-ink-muted">Für dieses Projekt sind noch keine Meilensteine geplant.</p>
                    @else
                        <div class="mb-4 flex h-2 overflow-hidden rounded-full bg-raised" aria-hidden="true">
                            @foreach ($milestoneStatuses as $status)
                                @php($anzahl = $stats['milestonesByStatus'][$status->value])
                                @if ($anzahl > 0)
                                    <span class="h-full" style="width: {{ $anzahl / $stats['milestonesTotal'] * 100 }}%; background: var(--pill-{{ $status->pillKind() }}-ink)" title="{{ $status->label() }}: {{ $anzahl }}"></span>
                                @endif
                            @endforeach
                        </div>

                        <ol class="flex flex-col divide-y divide-line">
                            @foreach ($project->milestones as $milestone)
                                <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0" wire:key="ms-{{ $milestone->id }}">
                                    <span class="flex min-w-0 flex-col">
                                        <span @class(['truncate text-[12.5px]', 'text-ink-base' => ! $milestone->status->countsAsSettled(), 'text-ink-muted line-through' => $milestone->status === \App\Enums\MilestoneStatus::Skipped, 'text-ink-muted' => $milestone->status === \App\Enums\MilestoneStatus::Done])>
                                            {{ $milestone->name }}
                                        </span>
                                        @if ($milestone->note)
                                            <span class="truncate text-[11px] text-ink-faint">{{ $milestone->note }}</span>
                                        @endif
                                    </span>
                                    <span class="flex flex-none items-center gap-2">
                                        <span @class(['tabular text-[11.5px]', 'text-ink-muted' => ! $milestone->isOverdue(), 'text-[color:var(--pill-bad-ink)]' => $milestone->isOverdue()])>
                                            {{ $milestone->due_date?->format('d.m.Y') ?? '—' }}
                                        </span>
                                        <x-status-pill :kind="$milestone->status->pillKind()" :label="$milestone->status->label()" />
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-panel>

                <x-panel title="Tickets zu diesem Projekt">
                    <div class="flex flex-col divide-y divide-line">
                        @forelse ($tickets as $ticket)
                            <a href="{{ route('customer-portal.tickets.show', $ticket) }}" wire:navigate wire:key="t-{{ $ticket->id }}"
                               class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate text-[12.5px] text-ink-base">{{ $ticket->subject }}</span>
                                    <span class="font-mono text-[10.5px] text-ink-faint">{{ $ticket->ticket_number }}</span>
                                </span>
                                <x-status-pill :kind="$ticket->status->pillKind()" :label="$ticket->status->label()" />
                            </a>
                        @empty
                            <p class="text-[12.5px] text-ink-muted">Keine Tickets zu diesem Projekt.</p>
                        @endforelse
                    </div>
                </x-panel>
            </div>

            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Betrieb</span>
                    @foreach ($project->operationsStatuses() as $label => $status)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[11.5px] text-ink-muted">{{ $label }}</span>
                            <x-status-pill :kind="$status->pillKind()" :label="$status->label()" />
                        </div>
                    @endforeach
                    <span class="text-[10.5px] text-ink-faint">
                        {{ $project->operations_checked_on ? 'Zuletzt geprüft am '.$project->operations_checked_on->format('d.m.Y') : 'Noch nicht geprüft' }}
                    </span>
                </div>

                <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Projekt</span>
                    @foreach ([
                        'Projektnummer' => $project->project_number,
                        'Typ' => $project->projectType?->name,
                        'Beginn' => $project->start_date?->format('d.m.Y'),
                        'Deadline' => $project->deadline?->format('d.m.Y'),
                        'Ansprechpartner' => $project->responsibleUser?->name,
                    ] as $label => $wert)
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[11.5px] text-ink-muted">{{ $label }}</span>
                            <span class="truncate text-right text-[12px] text-ink-base">{{ blank($wert) ? '—' : $wert }}</span>
                        </div>
                    @endforeach
                    @if ($project->description)
                        <p class="border-t border-line pt-3 text-[12px] leading-relaxed text-ink-muted">{{ $project->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </x-page>
</div>
