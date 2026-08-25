@php
    $spalten = $this->tableHeaders();
    $raster = $this->columnLayout();

    // Rastervorlage aus den sichtbaren Spalten, damit die Anteile des Entwurfs
    // auch beim Zu- und Abschalten stimmen.
    $vorlage = collect($spalten)
        ->map(fn (array $spalte): string => $raster[$spalte['index']]['breite'] ?? '1fr')
        ->implode(' ');

    $mindestbreite = max(620, count($spalten) * 150);
@endphp

<div>
    <x-page title="Artikel / Leistungen" subtitle="Der Katalog mit Standardpreisen und Abrechnungsintervallen.">
        <x-slot:actions>
            <x-button icon="plus" :href="route('products.create')" wire:navigate>Artikel anlegen</x-button>
        </x-slot:actions>

        <div class="grid items-start gap-3.5 lg:grid-cols-[264px_minmax(0,1fr)]">
            {{-- Linke Leiste: Kategorien mit Zählern, bleibt beim Blättern stehen. --}}
            <aside class="rounded-[10px] border border-line bg-panel lg:sticky lg:top-20">
                <div class="flex items-baseline justify-between gap-2.5 border-b border-line px-4 py-3.5">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">
                        Kategorien
                    </span>
                    <a href="{{ route('categories.index') }}"
                       wire:navigate
                       class="text-[11px] text-accent hover:underline">anlegen</a>
                </div>

                <div class="flex max-h-[70vh] flex-col overflow-y-auto p-1.5">
                    <button type="button"
                            wire:click="setCategory('')"
                            @class([
                                'flex items-center gap-2.5 rounded-[7px] px-2.5 py-2 text-left transition',
                                'bg-raised' => $categoryId === '',
                                'hover:bg-raised' => $categoryId !== '',
                            ])>
                        <span class="h-[7px] w-[7px] flex-none rounded-full bg-accent"></span>
                        <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink-base">Alle Kategorien</span>
                        <span class="tabular text-[11px] text-ink-muted">{{ $categoryTotal }}</span>
                    </button>

                    @foreach ($categories as $kategorie)
                        <button type="button"
                                wire:key="kategorie-{{ $kategorie['id'] }}"
                                wire:click="setCategory('{{ $kategorie['id'] }}')"
                                @class([
                                    'flex items-center gap-2.5 rounded-[7px] px-2.5 py-2 text-left transition',
                                    'pl-6' => $kategorie['unterkategorie'],
                                    'bg-raised' => $categoryId === (string) $kategorie['id'],
                                    'hover:bg-raised' => $categoryId !== (string) $kategorie['id'],
                                ])>
                            <span @class([
                                'h-[7px] w-[7px] flex-none rounded-full',
                                'bg-line-strong' => $kategorie['unterkategorie'],
                                'bg-ink-faint' => ! $kategorie['unterkategorie'],
                            ])></span>

                            <span class="flex min-w-0 flex-1 flex-col gap-px">
                                <span class="truncate text-[12.5px] text-ink-base">{{ $kategorie['name'] }}</span>
                                <span class="truncate text-[10.5px] text-ink-faint">{{ $kategorie['meta'] }}</span>
                            </span>

                            <span class="tabular text-[11px] text-ink-muted">{{ $kategorie['anzahl'] }}</span>
                        </button>
                    @endforeach

                    @if ($categories->isEmpty())
                        <p class="px-2.5 py-4 text-center text-[11.5px] text-ink-faint">
                            Noch keine Kategorie angelegt.
                        </p>
                    @endif
                </div>
            </aside>

            <div class="flex min-w-0 flex-col gap-3">
                {{-- Suche und Tag-Filter über der Schnellauswahl. --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="sm:w-72">
                        <x-input wire:model.live.debounce.300ms="search"
                                 label="Suche"
                                 placeholder="Name, interne Bezeichnung, Beschreibung"
                                 icon="magnifying-glass" />
                    </div>

                    <div class="flex gap-2 sm:ml-auto">
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
                <div class="flex flex-wrap items-center justify-between gap-2.5">
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
                        {{ trans_choice(':count Artikel|:count Artikel', $products->total(), ['count' => $products->total()]) }}
                    </span>
                </div>

                <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
                    <div class="overflow-x-auto">
                        {{--
                            Das Raster ist aus <div> gebaut. Ohne Rollen wäre es für
                            Screenreader eine Wand aus Text ohne Spaltenbezug.
                        --}}
                        <div role="table" aria-label="Artikel" style="min-width: {{ $mindestbreite }}px">
                            <div role="row" class="grid gap-3 border-b border-line bg-raised px-[17px] py-2.5"
                                 style="grid-template-columns: {{ $vorlage }}">
                                @foreach ($spalten as $spalte)
                                    <span role="columnheader" @class([
                                        'truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint',
                                        'text-right' => $raster[$spalte['index']]['rechts'] ?? false,
                                    ])>{{ $spalte['label'] }}</span>
                                @endforeach
                            </div>

                            @forelse ($products as $artikel)
                                {{--
                                    Die Zeile ist ein <div role="row">, kein <a>: ein Anker mit dieser Rolle
                                    verlöre seine Linkrolle. Der Link liegt in der ersten Zelle und spannt
                                    sich per `after` über die ganze Zeile — so bleiben Klick an jeder Stelle,
                                    Mittelklick, neuer Tab und Tastaturbedienung erhalten.
                                --}}
                                <div wire:key="artikel-{{ $artikel->id }}"
                                     role="row"
                                     class="relative grid items-center gap-3 border-b border-line px-[17px] py-3 transition hover:bg-raised focus-within:bg-raised"
                                     style="grid-template-columns: {{ $vorlage }}">
                                    @foreach ($spalten as $spalte)
                                    <div role="cell" @class([
                                        'min-w-0',
                                        'text-right' => $raster[$spalte['index']]['rechts'] ?? false,
                                    ])>
                                        @switch($spalte['index'])
                                            @case('article')
                                                <a href="{{ route('products.show', $artikel) }}"
                                                   wire:navigate
                                                   class="flex min-w-0 items-center gap-2.5 after:absolute after:inset-0 focus-visible:outline-none">
                                                    <span class="h-[7px] w-[7px] flex-none rounded-full"
                                                          style="background: var(--pill-{{ $artikel->isArchived() ? 'mute' : 'ok' }}-ink)"></span>

                                                    <div class="flex min-w-0 flex-col">
                                                        <span class="truncate text-[12.5px] font-medium text-ink-base">
                                                            {{ $artikel->name }}
                                                        </span>
                                                        <span class="truncate font-mono text-[10.5px] text-ink-faint">
                                                            {{ $artikel->internal_name }}
                                                        </span>
                                                    </div>
                                                </a>
                                                @break

                                            @case('category')
                                                <span class="truncate text-[12px] text-ink-base">
                                                    {{ $artikel->subcategory?->name ?? $artikel->category?->name ?? '—' }}
                                                </span>
                                                @break

                                            @case('interval')
                                                <span class="truncate text-[12px] text-ink-muted">
                                                    {{ $artikel->defaultBillingInterval()->label() }}
                                                </span>
                                                @break

                                            @case('default_sales_price_cents')
                                                <span class="truncate tabular text-right text-[12.5px] text-ink">
                                                    {{ $artikel->defaultSalesPrice()->format() }}
                                                </span>
                                                @break

                                            @case('contracts')
                                                <span class="tabular text-right text-[12.5px] text-ink-muted">
                                                    {{ $artikel->contracts_count }}
                                                </span>
                                                @break

                                            @case('status')
                                                <span>
                                                    <x-status-pill :kind="$artikel->isArchived() ? 'mute' : 'ok'"
                                                                   :label="$artikel->status->label()" />
                                                </span>
                                                @break

                                            @case('default_purchase_price_cents')
                                                <span class="truncate tabular text-right text-[12.5px] text-ink-muted">
                                                    {{ $artikel->defaultPurchasePrice()->format() }}
                                                </span>
                                                @break

                                            @case('margin')
                                                <span @class([
                                                    'truncate tabular text-right text-[12.5px]',
                                                    'text-[color:var(--pill-bad-ink)]' => $artikel->defaultMargin()->isNegative(),
                                                    'text-ink-base' => ! $artikel->defaultMargin()->isNegative(),
                                                ])>{{ $artikel->defaultMargin()->format() }}</span>
                                                @break

                                            @case('variants_count')
                                                <span class="tabular text-right text-[12.5px] text-ink-muted">
                                                    {{ $artikel->variants_count }}
                                                </span>
                                                @break

                                        @endswitch
                                    </div>
                                    @endforeach
                                </div>
                            @empty
                                <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                                    Kein Artikel passt zu Kategorie und Suche.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    @if ($products->hasPages())
                        <div class="border-t border-line px-[17px] py-3">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
