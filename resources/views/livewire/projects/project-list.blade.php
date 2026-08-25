@php
    $spalten = $this->tableHeaders();
    $raster = $this->columnLayout();

    // Rastervorlage aus den sichtbaren Spalten, damit die Anteile des Entwurfs
    // auch dann stimmen, wenn Spalten zu- oder abgeschaltet werden.
    $vorlage = collect($spalten)
        ->map(fn (array $spalte): string => $raster[$spalte['index']]['breite'] ?? '1fr')
        ->implode(' ');

    // Genug Platz je Spalte, damit Betraege und Ampeln nicht umbrechen; bei
    // vielen zugeschalteten Spalten scrollt das Raster quer.
    $mindestbreite = max(720, count($spalten) * 132);
@endphp

<div>
    <x-page title="Projekte" subtitle="Alle Kundenprojekte mit Umsatz und Betriebsstatus.">
        <x-slot:actions>
            <x-button icon="plus" :href="route('projects.create')" wire:navigate>Projekt anlegen</x-button>
        </x-slot:actions>

        {{-- Kennzahlen der Uebersicht — alle vier aus echten Daten. --}}
        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-tile label="Offene Projekte"
                        :value="$metrics['open']"
                        note="Geplant, laufend oder pausiert" />

            <x-kpi-tile label="Umsatz"
                        :value="$metrics['revenue']->format()"
                        note="Einmalige Positionen offener Projekte" />

            <x-kpi-tile label="Umsatz / Mon."
                        :value="$metrics['monthlyRevenue']->format()"
                        note="Wiederkehrende Positionen, auf den Monat gerechnet" />

            <x-kpi-tile label="Betrieb prüfen"
                        :value="$metrics['needsAttention']"
                        note="Backup, Security oder Updates nicht in Ordnung" />
        </div>

        {{-- Suche und die feineren Filter über der Schnellauswahl. --}}
        <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="lg:w-72">
                <x-input wire:model.live.debounce.300ms="search"
                         label="Suche"
                         placeholder="Projektnummer, Name, Kunde"
                         icon="magnifying-glass" />
            </div>

            <div class="lg:w-48">
                <x-select.styled wire:model.live="projectTypeId"
                                 label="Typ"
                                 placeholder="Alle"
                                 :options="$projectTypes"
                                 select="label:name|value:id" />
            </div>

            <div class="lg:w-56">
                <x-select.styled wire:model.live="responsibleUserId"
                                 label="Verantwortlich"
                                 placeholder="Alle"
                                 :options="$responsibleUsers"
                                 select="label:name|value:id" />
            </div>

            <div class="flex gap-2 lg:ml-auto">
                <x-button color="secondary" outline sm wire:click="resetFilters">Filter zurücksetzen</x-button>

                <x-button color="secondary"
                          outline
                          sm
                          icon="adjustments-horizontal"
                          wire:click="$set('showTableSettings', true)"
                          title="Spalten einrichten" />
            </div>
        </div>

        {{-- Schnellauswahl nach Status, mit Zählern wie im Entwurf. --}}
        <div class="mb-3.5 flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($this->statusFilters() as $filter)
                    @php $aktiv = $status === $filter['wert']; @endphp

                    <button type="button"
                            wire:click="setStatus('{{ $filter['wert'] }}')"
                            @class([
                                'inline-flex items-center gap-2 rounded-[7px] border px-2.5 py-1.5 text-[11.5px] font-medium transition',
                                'border-accent bg-accent text-accent-ink' => $aktiv,
                                'border-line bg-panel text-ink-muted hover:border-line-strong hover:text-ink-base' => ! $aktiv,
                            ])>
                        {{ $filter['label'] }}
                        <span @class([
                            'rounded-full px-1.5 py-px font-mono text-[10px] tabular-nums',
                            'bg-accent-ink/15' => $aktiv,
                            'bg-raised text-ink-faint' => ! $aktiv,
                        ])>{{ $filter['anzahl'] }}</span>
                    </button>
                @endforeach
            </div>

            <span class="text-[11.5px] text-ink-faint">
                {{ trans_choice(':count Projekt|:count Projekte', $projects->total(), ['count' => $projects->total()]) }}
            </span>
        </div>

        <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
            <div class="overflow-x-auto">
                {{--
                    Das Raster ist aus <div> gebaut. Ohne Rollen wäre es für
                    Screenreader eine Wand aus Text ohne Spaltenbezug.
                --}}
                <div role="table" aria-label="Projekte" style="min-width: {{ $mindestbreite }}px">
                    {{-- Kopfzeile --}}
                    <div role="row" class="grid gap-3.5 border-b border-line bg-raised px-[17px] py-2.5"
                         style="grid-template-columns: {{ $vorlage }}">
                        @foreach ($spalten as $spalte)
                            <span role="columnheader" @class([
                                'truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint',
                                'text-right' => $raster[$spalte['index']]['rechts'] ?? false,
                            ])>{{ $spalte['label'] }}</span>
                        @endforeach
                    </div>

                    @forelse ($projects as $projekt)
                        @php
                            $fortschritt = $projekt->progressPercentage();
                            $tage = $projekt->daysUntilDeadline();
                        @endphp

                        {{--
                            Die Zeile ist ein <div role="row">, kein <a>: ein Anker mit dieser Rolle
                            verlöre seine Linkrolle. Der Link liegt in der ersten Zelle und spannt
                            sich per `after` über die ganze Zeile — so bleiben Klick an jeder Stelle,
                            Mittelklick, neuer Tab und Tastaturbedienung erhalten.
                        --}}
                        <div wire:key="projekt-{{ $projekt->id }}"
                             role="row"
                             class="relative grid items-center gap-3.5 border-b border-line px-[17px] py-3 transition hover:bg-raised focus-within:bg-raised"
                             style="grid-template-columns: {{ $vorlage }}">
                            @foreach ($spalten as $spalte)
                            <div role="cell" @class([
                                'min-w-0',
                                'text-right' => $raster[$spalte['index']]['rechts'] ?? false,
                            ])>
                                @switch($spalte['index'])
                                    @case('project')
                                        <a href="{{ route('projects.show', $projekt) }}"
                                           wire:navigate
                                           class="flex min-w-0 items-center gap-[11px] after:absolute after:inset-0 focus-visible:outline-none">
                                            <x-project-type-icon :type="$projekt->projectType" size="sm" />

                                            <div class="flex min-w-0 flex-col">
                                                <span class="truncate text-[13px] font-medium text-ink-base">
                                                    {{ $projekt->name }}
                                                </span>
                                                <span class="truncate text-[10.5px] text-ink-faint">
                                                    {{ $projekt->project_number }}
                                                    @if ($projekt->projectType)
                                                        · {{ $projekt->projectType->name }}
                                                    @endif
                                                </span>
                                            </div>
                                        </a>
                                        @break

                                    @case('customer')
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate text-[12.5px] text-ink-base">
                                                {{ $projekt->customer->displayName() }}
                                            </span>
                                            <span class="truncate tabular text-[10.5px] text-ink-faint">
                                                {{ $projekt->customer->customer_number }}
                                            </span>
                                        </div>
                                        @break

                                    @case('progress')
                                        <div class="min-w-0">
                                            @if ($fortschritt === null)
                                                {{-- Ohne Meilensteine gibt es nichts zu messen. --}}
                                                <span class="text-[11.5px] text-ink-faint">Keine Meilensteine</span>
                                            @else
                                                <div class="flex items-center gap-2.5">
                                                    {{-- Die Leiste traegt keine eigenen Attribute nach aussen, darum der Rahmen. --}}
                                                    <div class="min-w-0 flex-1">
                                                        <x-progress :percent="$fortschritt"
                                                                        xs
                                                                        without-text
                                                                        color="primary" />
                                                    </div>
                                                    <span class="tabular w-9 flex-none text-right text-[11px] text-ink-muted">
                                                        {{ $fortschritt }}%
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                        @break

                                    @case('volume')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink">
                                            {{ $projekt->oneTimeVolume()->format() }}
                                        </span>
                                        @break

                                    @case('deadline')
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate tabular text-[12.5px] text-ink-base">
                                                {{ $projekt->deadline?->format('d.m.Y') ?? '—' }}
                                            </span>

                                            @if ($tage !== null && $projekt->status->isOpen())
                                                <span @class([
                                                    'truncate text-[10.5px]',
                                                    'text-[color:var(--pill-bad-ink)]' => $tage < 0,
                                                    'text-[color:var(--pill-warn-ink)]' => $tage >= 0 && $tage <= 14,
                                                    'text-ink-faint' => $tage > 14,
                                                ])>
                                                    @if ($tage < 0)
                                                        {{ abs($tage) }} Tage überfällig
                                                    @elseif ($tage === 0)
                                                        heute fällig
                                                    @else
                                                        noch {{ $tage }} Tage
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                        @break

                                    @case('backup_status')
                                    @case('security_status')
                                    @case('update_status')
                                        @php
                                            $betrieb = $projekt->{$spalte['index']};
                                        @endphp
                                        <span>
                                            <x-status-pill :kind="$betrieb->pillKind()"
                                                           :label="$betrieb->shortLabel()"
                                                           :title="$betrieb->label()" />
                                        </span>
                                        @break

                                    @case('operations_checked_on')
                                        <span class="tabular text-[12px] text-ink-muted">
                                            {{ $projekt->operations_checked_on?->format('d.m.Y') ?? '—' }}
                                        </span>
                                        @break

                                    @case('status')
                                        <span>
                                            <x-status-pill :kind="$projekt->status->pillKind()"
                                                           :label="$projekt->status->label()" />
                                        </span>
                                        @break

                                    @case('project_number')
                                        <span class="tabular text-[12px] text-ink-muted">{{ $projekt->project_number }}</span>
                                        @break

                                    @case('type')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            {{ $projekt->projectType?->name ?? '—' }}
                                        </span>
                                        @break

                                    @case('responsible')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            {{ $projekt->responsibleUser?->name ?? '—' }}
                                        </span>
                                        @break

                                    @case('start_date')
                                        <span class="tabular text-[12px] text-ink-muted">
                                            {{ $projekt->start_date?->format('d.m.Y') ?? '—' }}
                                        </span>
                                        @break

                                    @case('recurring_volume')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink-muted">
                                            {{ $projekt->recurringVolume()->format() }}
                                        </span>
                                        @break

                                    @case('milestones')
                                        <span class="tabular text-right text-[12.5px] text-ink-muted">
                                            {{ $projekt->milestones->count() }}
                                        </span>
                                        @break
                                @endswitch
                            </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                            Kein Projekt passt zu Filter und Suche.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($projects->hasPages())
                <div class="border-t border-line px-[17px] py-3">
                    {{ $projects->links() }}
                </div>
            @endif
        </div>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
