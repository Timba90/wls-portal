@php
    $spalten = $this->tableHeaders();
    $raster = $this->columnLayout();

    // Rastervorlage aus den sichtbaren Spalten, damit die Anteile auch dann
    // stimmen, wenn Spalten zu- oder abgeschaltet werden.
    $vorlage = collect($spalten)
        ->map(fn (array $spalte): string => $raster[$spalte['index']]['breite'] ?? '1fr')
        ->implode(' ');

    $mindestbreite = max(760, count($spalten) * 150);
@endphp

<div>
    <x-page title="Ansprechpartner" subtitle="Ansprechpartner der Firmenkunden mit Rollen und Kontaktdaten.">
        <x-slot:actions>
            <x-button color="secondary" outline :href="route('contact-roles.index')" wire:navigate>
                Rollen verwalten
            </x-button>

            <x-button icon="plus" :href="route('contacts.create')" wire:navigate>Ansprechpartner anlegen</x-button>
        </x-slot:actions>

        <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="lg:w-80">
                <x-input wire:model.live.debounce.300ms="search"
                         label="Suche"
                         placeholder="Name, E-Mail, Telefon oder Kunde"
                         icon="magnifying-glass" />
            </div>

            <div class="lg:w-44">
                <x-select.styled wire:model.live="status"
                                 label="Status"
                                 placeholder="Alle"
                                 :options="[
                                     ['label' => 'Aktiv', 'value' => 'active'],
                                     ['label' => 'Archiviert', 'value' => 'archived'],
                                 ]"
                                 select="label:label|value:value" />
            </div>

            <div class="lg:w-56">
                <x-select.styled wire:model.live="roleId"
                                 label="Rolle"
                                 placeholder="Alle"
                                 :options="$roles"
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

        <div class="mb-3.5 flex flex-wrap items-center justify-end gap-2.5">
            <span class="text-[11.5px] text-ink-faint">
                {{ trans_choice(':count Ansprechpartner|:count Ansprechpartner', $contacts->total(), ['count' => number_format($contacts->total(), 0, ',', '.')]) }}
                in der Auswahl
            </span>
        </div>

        <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
            <div class="overflow-x-auto">
                {{--
                    Das Raster ist aus <div> gebaut. Ohne Rollen wäre es für
                    Screenreader eine Wand aus Text ohne Spaltenbezug.
                --}}
                <div role="table" aria-label="Ansprechpartner" style="min-width: {{ $mindestbreite }}px">
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

                    @forelse ($contacts as $kontakt)
                        {{--
                            Die ganze Zeile ist der Link. Die Kundenspalte enthält
                            deshalb bewusst keine eigenen Links: verschachtelte
                            Anker sind ungültiges HTML und die Tastaturbedienung
                            würde daran hängenbleiben.
                        --}}
                        <div wire:key="kontakt-{{ $kontakt->id }}"
                             role="row"
                             class="relative grid items-center gap-3.5 border-b border-line px-[17px] py-3 transition hover:bg-raised focus-within:bg-raised"
                             style="grid-template-columns: {{ $vorlage }}">
                            @foreach ($spalten as $spalte)
                            <div role="cell" @class([
                                'min-w-0',
                                'text-right' => $raster[$spalte['index']]['rechts'] ?? false,
                            ])>
                                @switch($spalte['index'])
                                    @case('name')
                                        <a href="{{ route('contacts.show', $kontakt) }}"
                                           wire:navigate
                                           class="flex min-w-0 items-center gap-[11px] after:absolute after:inset-0 focus-visible:outline-none">
                                            <x-avatar-initials :initials="$kontakt->initials()" size="sm" />

                                            <span class="truncate text-[13px] font-medium text-ink-base">
                                                {{ $kontakt->listName() }}
                                            </span>
                                        </a>
                                        @break

                                    @case('email')
                                        <span class="truncate font-mono text-[11.5px] text-ink-muted">
                                            {{ $kontakt->primaryEmailAddress()?->email ?? '—' }}
                                        </span>
                                        @break

                                    @case('phone')
                                        <span class="truncate tabular text-[12px] text-ink-muted">
                                            {{ $kontakt->primaryPhoneNumber()?->number ?? '—' }}
                                        </span>
                                        @break

                                    @case('customers')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            {{ $kontakt->assignments->map(fn ($zuordnung) => $zuordnung->customer->short_label)->implode(', ') ?: '—' }}
                                        </span>
                                        @break

                                    @case('roles')
                                        <div class="flex min-w-0 flex-wrap gap-1">
                                            @forelse ($kontakt->assignments->flatMap->roles->unique('id') as $rolle)
                                                <x-status-pill kind="mute" :label="$rolle->name" :dot="false" />
                                            @empty
                                                <span class="text-[12px] text-ink-faint">—</span>
                                            @endforelse
                                        </div>
                                        @break

                                    @case('preferred_contact_method')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            {{ $kontakt->preferred_contact_method?->label() ?? '—' }}
                                        </span>
                                        @break

                                    @case('status')
                                        <span>
                                            <x-status-pill :kind="$kontakt->isArchived() ? 'mute' : 'ok'"
                                                           :label="$kontakt->isArchived() ? 'Archiviert' : 'Aktiv'" />
                                        </span>
                                        @break
                                @endswitch
                            </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                            Kein Ansprechpartner passt zu Filter und Suche.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($contacts->hasPages())
                <div class="border-t border-line px-[17px] py-3">
                    {{ $contacts->links() }}
                </div>
            @endif
        </div>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
