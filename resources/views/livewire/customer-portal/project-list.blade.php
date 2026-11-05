<div>
    <x-page title="Projekte" subtitle="Fortschritt, Termine und Betriebsstatus Ihrer Projekte.">
        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-tile label="Laufende Projekte" :value="$stats['open']" :note="$stats['completed'].' abgeschlossen'" />
            <x-kpi-tile label="Ø Fortschritt"
                        :value="$stats['averageProgress'] === null ? '—' : $stats['averageProgress'].' %'"
                        note="Über alle laufenden Projekte" />
            <x-kpi-tile label="Meilensteine"
                        :value="$stats['milestonesDone'].' / '.$stats['milestonesTotal']"
                        note="Erledigt in laufenden Projekten" />
            <x-kpi-tile label="Überfällig"
                        :value="$stats['overdueMilestones']"
                        note="Meilensteine nach ihrem Termin" />
        </div>

        <div class="mb-3 flex gap-1 self-start rounded-[9px] border border-line bg-panel p-1 sm:w-fit">
            @foreach (['laufend' => 'Laufend', 'abgeschlossen' => 'Abgeschlossen', 'alle' => 'Alle'] as $schluessel => $beschriftung)
                <button type="button"
                        wire:click="$set('view', '{{ $schluessel }}')"
                        @class([
                            'whitespace-nowrap rounded-[6px] px-3 py-1.5 text-[12px] font-medium transition',
                            'bg-accent text-accent-ink' => $view === $schluessel,
                            'text-ink-muted hover:bg-raised hover:text-ink-base' => $view !== $schluessel,
                        ])>{{ $beschriftung }}</button>
            @endforeach
        </div>

        <div class="grid gap-3.5 lg:grid-cols-2">
            @forelse ($projects as $project)
                @php($fortschritt = $project->progressPercentage())
                @php($tage = $project->daysUntilDeadline())
                <a href="{{ route('customer-portal.projects.show', $project) }}"
                   wire:navigate
                   wire:key="projekt-{{ $project->id }}"
                   class="flex flex-col gap-3 rounded-[10px] border border-line bg-panel px-4 py-4 transition hover:border-line-strong">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-project-type-icon :type="$project->projectType" />
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-[13px] font-semibold text-ink">{{ $project->name }}</span>
                                <span class="font-mono text-[10.5px] text-ink-faint">{{ $project->project_number }}</span>
                            </span>
                        </div>
                        <x-status-pill :kind="$project->status->pillKind()" :label="$project->status->label()" />
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between text-[11px]">
                            <span class="text-ink-muted">Fortschritt</span>
                            <span class="tabular text-ink">{{ $fortschritt === null ? 'Keine Meilensteine' : $fortschritt.' %' }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-raised">
                            <div class="h-full rounded-full bg-accent" style="width: {{ $fortschritt ?? 0 }}%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-[11px]">
                        <div class="flex flex-col">
                            <span class="text-ink-faint">Deadline</span>
                            <span @class(['tabular', 'text-ink-base' => ! $project->isOverdue(), 'text-[color:var(--pill-bad-ink)]' => $project->isOverdue()])>
                                {{ $project->deadline?->format('d.m.Y') ?? '—' }}
                            </span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-ink-faint">Restlaufzeit</span>
                            <span class="tabular text-ink-base">
                                @if ($tage === null) — @elseif ($tage < 0) {{ abs($tage) }} Tage drüber @else {{ $tage }} Tage @endif
                            </span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-ink-faint">Offene Tickets</span>
                            <span class="tabular text-ink-base">{{ $project->open_tickets_count }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <x-panel class="lg:col-span-2">
                    <p class="text-[12.5px] text-ink-muted">Keine Projekte in dieser Ansicht.</p>
                </x-panel>
            @endforelse
        </div>

        @if ($stats['upcomingMilestones']->isNotEmpty())
            <x-panel title="Nächste Meilensteine" class="mt-3.5">
                <div class="flex flex-col divide-y divide-line">
                    @foreach ($stats['upcomingMilestones'] as $milestone)
                        <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0" wire:key="ms-{{ $milestone->id }}">
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-[12.5px] text-ink-base">{{ $milestone->name }}</span>
                                <span class="truncate text-[10.5px] text-ink-faint">{{ $milestone->project->name }}</span>
                            </span>
                            <span class="flex flex-none items-center gap-2">
                                <span @class(['tabular text-[11.5px]', 'text-ink-muted' => ! $milestone->isOverdue(), 'text-[color:var(--pill-bad-ink)]' => $milestone->isOverdue()])>
                                    {{ $milestone->due_date->format('d.m.Y') }}
                                </span>
                                <x-status-pill :kind="$milestone->status->pillKind()" :label="$milestone->status->label()" />
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif
    </x-page>
</div>
