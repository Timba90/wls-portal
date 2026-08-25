@php
    $spalten = $this->tableHeaders();
    $raster = $this->columnLayout();

    // Rastervorlage aus den sichtbaren Spalten, damit die Anteile des Entwurfs
    // auch dann stimmen, wenn Spalten zu- oder abgeschaltet werden.
    $vorlage = collect($spalten)
        ->map(fn (array $spalte): string => $raster[$spalte['index']]['breite'] ?? '1fr')
        ->implode(' ');

    $mindestbreite = max(640, count($spalten) * 160);
@endphp

<div>
    <x-page title="Kunden" subtitle="Alle Firmen- und Privatkunden mit ihren Kennzahlen.">
        <x-slot:actions>
            <x-button icon="plus" :href="route('customers.create')" wire:navigate>Kunde anlegen</x-button>
        </x-slot:actions>

        {{-- Suche und die feineren Filter über der Schnellauswahl. --}}
        <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="lg:w-72">
                <x-input wire:model.live.debounce.300ms="search"
                         label="Suche"
                         placeholder="Nummer, Name, Kurzbezeichnung, Kürzel"
                         icon="magnifying-glass" />
            </div>

            <div class="lg:w-44">
                <x-select.styled wire:model.live="type"
                                 label="Typ"
                                 placeholder="Alle"
                                 :options="$typeOptions"
                                 select="label:label|value:value" />
            </div>

            <div class="lg:w-56">
                <x-select.styled wire:model.live="responsibleUserId"
                                 label="Interner Verantwortlicher"
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
                {{ trans_choice(':count Kunde|:count Kunden', $customers->total(), ['count' => $customers->total()]) }}
            </span>
        </div>

        <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
            <div class="overflow-x-auto">
                {{--
                    Das Raster ist aus <div> gebaut, nicht aus <table> — die
                    Spaltenanteile des Entwurfs lassen sich so sauber setzen.
                    Ohne Rollen wäre die Tabelle für Screenreader aber eine
                    Wand aus Text ohne Spaltenbezug, deshalb tragen Rahmen,
                    Kopfzeile und Zellen sie ausdrücklich.
                --}}
                <div role="table" aria-label="Kunden" style="min-width: {{ $mindestbreite }}px">
                    <div role="row"
                         class="grid gap-3.5 border-b border-line bg-raised px-[17px] py-2.5"
                         style="grid-template-columns: {{ $vorlage }}">
                        @foreach ($spalten as $spalte)
                            <span role="columnheader" @class([
                                'truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint',
                                'text-right' => $raster[$spalte['index']]['rechts'] ?? false,
                            ])>{{ $spalte['label'] }}</span>
                        @endforeach
                    </div>

                    @forelse ($customers as $kunde)
                        {{--
                            Die Zeile ist ein <div role="row">, kein <a>: ein Anker mit dieser Rolle
                            verlöre seine Linkrolle. Der Link liegt in der ersten Zelle und spannt
                            sich per `after` über die ganze Zeile — so bleiben Klick an jeder Stelle,
                            Mittelklick, neuer Tab und Tastaturbedienung erhalten.
                        --}}
                        <div wire:key="kunde-{{ $kunde->id }}"
                             role="row"
                             class="relative grid items-center gap-3.5 border-b border-line px-[17px] py-3 transition hover:bg-raised focus-within:bg-raised"
                             style="grid-template-columns: {{ $vorlage }}">
                            @foreach ($spalten as $spalte)
                            <div role="cell" @class([
                                'min-w-0',
                                'text-right' => $raster[$spalte['index']]['rechts'] ?? false,
                            ])>
                                @switch($spalte['index'])
                                    @case('customer')
                                        <a href="{{ route('customers.show', $kunde) }}"
                                           wire:navigate
                                           class="flex min-w-0 items-center gap-[11px] after:absolute after:inset-0 focus-visible:outline-none">
                                            <x-avatar-initials :initials="$kunde->initials()" size="sm" />

                                            <div class="flex min-w-0 flex-col">
                                                <span class="truncate text-[13px] font-medium text-ink-base">
                                                    {{ $kunde->displayName() }}
                                                </span>
                                                <span class="truncate text-[10.5px] text-ink-faint">
                                                    {{ $kunde->customer_number }} · {{ $kunde->short_label }}
                                                </span>
                                            </div>
                                        </a>
                                        @break

                                    @case('contact')
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate text-[12.5px] text-ink-base">
                                                {{ $kunde->primaryContactName() ?? '—' }}
                                            </span>
                                            <span class="truncate font-mono text-[10.5px] text-ink-faint">
                                                {{ $kunde->primaryContactEmail() ?? '' }}
                                            </span>
                                        </div>
                                        @break

                                    @case('active_services_count')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink-muted">
                                            {{ $kunde->active_services_count }}
                                        </span>
                                        @break

                                    @case('monthly_revenue')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink">
                                            {{ $kunde->monthlyRevenue()->format() }}
                                        </span>
                                        @break

                                    @case('activity')
                                        <span class="truncate text-[11.5px] text-ink-muted">
                                            {{ $kunde->updated_at?->diffForHumans(short: true) ?? '—' }}
                                        </span>
                                        @break

                                    @case('status')
                                        <span>
                                            <x-status-pill :kind="$kunde->isArchived() ? 'mute' : 'ok'"
                                                           :label="$kunde->status->label()" />
                                        </span>
                                        @break

                                    @case('customer_number')
                                        <span class="tabular text-[12px] text-ink-muted">{{ $kunde->customer_number }}</span>
                                        @break

                                    @case('internal_code')
                                        <span class="font-mono text-[11.5px] text-ink-muted">{{ $kunde->internal_code }}</span>
                                        @break

                                    @case('type')
                                        <span class="text-[12px] text-ink-muted">{{ $kunde->type->label() }}</span>
                                        @break

                                    @case('responsible')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            {{ $kunde->responsibleUser?->name ?? '—' }}
                                        </span>
                                        @break

                                    @case('yearly_revenue')
                                        <span class="tabular text-right text-[12.5px] text-ink-base">
                                            {{ $kunde->yearlyRevenue()->format() }}
                                        </span>
                                        @break

                                    @case('monthly_costs')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink-muted">
                                            {{ $kunde->monthlyCosts()->format() }}
                                        </span>
                                        @break

                                    @case('margin')
                                        <span @class([
                                            'tabular text-right text-[12.5px]',
                                            'text-[color:var(--pill-bad-ink)]' => $kunde->monthlyMargin()->isNegative(),
                                            'text-ink-base' => ! $kunde->monthlyMargin()->isNegative(),
                                        ])>{{ $kunde->monthlyMargin()->format() }}</span>
                                        @break
                                @endswitch
                            </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                            Kein Kunde passt zu Filter und Suche.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($customers->hasPages())
                <div class="border-t border-line px-[17px] py-3">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
