@php
    $spalten = $this->tableHeaders();
    $raster = $this->columnLayout();

    $vorlage = collect($spalten)
        ->map(fn (array $spalte): string => $raster[$spalte['index']]['breite'] ?? '1fr')
        ->implode(' ');

    $mindestbreite = max(760, count($spalten) * 150);
@endphp

<div>
    <x-page title="Zertifikate" subtitle="Der importierte Zertifikatsbestand mit Ablauf und Zuordnung.">
        <x-slot:actions>
            <x-button color="secondary" outline :href="route('domains.index')" wire:navigate>
                Domains
            </x-button>
        </x-slot:actions>

        {{-- Kennzahlen des Bestands — alle vier aus echten Daten. --}}
        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-tile label="Zertifikate"
                        :value="number_format($metrics['total'], 0, ',', '.')"
                        note="Aus allen Anbietern zusammen" />

            <x-kpi-tile label="Ohne Kunde"
                        :value="number_format($metrics['unassigned'], 0, ',', '.')"
                        note="Der Registrar kennt unsere Kunden nicht" />

            <x-kpi-tile label="Läuft bald ab"
                        :value="number_format($metrics['expiringSoon'], 0, ',', '.')"
                        note="In den nächsten 30 Tagen" />

            <x-kpi-tile label="Abgelaufen"
                        :value="number_format($metrics['expired'], 0, ',', '.')"
                        note="Ablaufdatum verstrichen" />
        </div>

        <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="lg:w-80">
                <x-input wire:model.live.debounce.300ms="search"
                         label="Suche"
                         placeholder="Hauptname"
                         icon="magnifying-glass" />
            </div>

            <div class="lg:w-48">
                <x-select.styled wire:model.live="provider"
                                 label="Anbieter"
                                 placeholder="Alle"
                                 :options="$providerOptions"
                                 select="label:label|value:value" />
            </div>

            <div class="lg:w-48">
                <x-select.styled wire:model.live="assignment"
                                 label="Zuordnung"
                                 placeholder="Alle"
                                 :options="[
                                     ['label' => 'Ohne Kunde', 'value' => 'unassigned'],
                                     ['label' => 'Zugeordnet', 'value' => 'assigned'],
                                 ]"
                                 select="label:label|value:value" />
            </div>

            <div class="lg:w-48">
                <x-select.styled wire:model.live="expiry"
                                 label="Ablauf"
                                 placeholder="Alle"
                                 :options="[
                                     ['label' => 'Läuft bald ab', 'value' => 'soon'],
                                     ['label' => 'Abgelaufen', 'value' => 'expired'],
                                 ]"
                                 select="label:label|value:value" />
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

        <div class="mb-3.5 flex flex-wrap items-center justify-end gap-2.5">
            <span class="text-[11.5px] text-ink-faint">
                {{ trans_choice(':count Zertifikat|:count Zertifikate', $certificates->total(), ['count' => number_format($certificates->total(), 0, ',', '.')]) }}
                in der Auswahl
            </span>
        </div>

        <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
            <div class="overflow-x-auto">
                {{--
                    Das Raster ist aus <div> gebaut. Ohne Rollen wäre es für
                    Screenreader eine Wand aus Text ohne Spaltenbezug.
                --}}
                <div role="table" aria-label="Zertifikate" style="min-width: {{ $mindestbreite }}px">
                    <div role="row" class="grid gap-3.5 border-b border-line bg-raised px-[17px] py-2.5"
                         style="grid-template-columns: {{ $vorlage }}">
                        @foreach ($spalten as $spalte)
                            <span role="columnheader"
                                  class="truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">
                                {{ $spalte['label'] }}
                            </span>
                        @endforeach
                    </div>

                    @forelse ($certificates as $zertifikat)
                        @php
                            $tage = $zertifikat->daysUntilExpiry();
                        @endphp

                        <div wire:key="zertifikat-{{ $zertifikat->id }}"
                             role="row"
                             class="grid items-center gap-3.5 border-b border-line px-[17px] py-3 transition hover:bg-raised"
                             style="grid-template-columns: {{ $vorlage }}">
                            @foreach ($spalten as $spalte)
                            <div role="cell" class="min-w-0">
                                @switch($spalte['index'])
                                    @case('certificate')
                                        <span class="truncate font-mono text-[12.5px] text-ink-base">{{ $zertifikat->common_name }}</span>
                                        @break

                                    @case('customer')
                                        @if ($zertifikat->customer)
                                            <span class="truncate text-[12.5px] text-ink-base">
                                                {{ $zertifikat->customer->displayName() }}
                                            </span>
                                        @else
                                            <x-status-pill kind="warn" label="Ohne Kunde" />
                                        @endif
                                        @break

                                    @case('expires_on')
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate tabular text-[12.5px] text-ink-base">
                                                {{ $zertifikat->expires_on?->format('d.m.Y') ?? '—' }}
                                            </span>

                                            @if ($tage !== null)
                                                <span @class([
                                                    'truncate text-[10.5px]',
                                                    'text-[color:var(--pill-bad-ink)]' => $tage < 0,
                                                    'text-[color:var(--pill-warn-ink)]' => $tage >= 0 && $tage <= 30,
                                                    'text-ink-faint' => $tage > 30,
                                                ])>
                                                    @if ($tage < 0)
                                                        seit {{ abs($tage) }} Tagen abgelaufen
                                                    @elseif ($tage === 0)
                                                        läuft heute ab
                                                    @else
                                                        noch {{ $tage }} Tage
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                        @break

                                    @case('issuer')
                                        <span class="truncate text-[12px] text-ink-muted">{{ $zertifikat->issuer ?? '—' }}</span>
                                        @break

                                    @case('provider')
                                        <span class="truncate text-[12px] text-ink-muted">{{ $zertifikat->provider->label() }}</span>
                                        @break

                                    @case('status')
                                        <span class="truncate font-mono text-[11.5px] text-ink-muted">{{ $zertifikat->status }}</span>
                                        @break

                                    @case('alternative_names')
                                        <span class="truncate font-mono text-[11px] text-ink-muted">
                                            {{ implode(', ', $zertifikat->alternative_names ?? []) ?: '—' }}
                                        </span>
                                        @break

                                    @case('issued_on')
                                        <span class="tabular text-[12px] text-ink-muted">
                                            {{ $zertifikat->issued_on?->format('d.m.Y') ?? '—' }}
                                        </span>
                                        @break

                                    @case('synced_at')
                                        <span class="tabular text-[12px] text-ink-muted">
                                            {{ $zertifikat->synced_at?->format('d.m.Y H:i') ?? '—' }}
                                        </span>
                                        @break
                                @endswitch
                            </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                            Kein Bestand. Eingelesen wird über <span class="font-mono">php artisan registrar:import</span>.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($certificates->hasPages())
                <div class="border-t border-line px-[17px] py-3">
                    {{ $certificates->links() }}
                </div>
            @endif
        </div>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
