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
    <x-page title="Leistungsübersicht" subtitle="Alle Kundenleistungen mit vereinbartem Preis, Kosten und Abrechnungsintervall.">

        <div class="mb-3.5 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile label="Umsatz / Monat"
                        :value="$summe['monthlyRevenue']->format()"
                        :note="$summe['yearlyRevenue']->format().' im Jahr'" />
            <x-kpi-tile label="Kosten / Monat" :value="$summe['monthlyCosts']->format()" />
            <x-kpi-tile label="Marge / Monat" :value="$summe['monthlyMargin']->format()" />
            <x-kpi-tile label="In den Kennzahlen"
                        :value="number_format($summe['count'], 0, ',', '.')"
                        note="aktiv, wiederkehrend, abzurechnen" />
        </div>

        <div class="mb-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-input wire:model.live.debounce.300ms="search"
                         label="Suche"
                         placeholder="Leistung, Rechnungsbezeichnung oder Kunde"
                         icon="magnifying-glass" />

                <x-select.styled wire:model.live="status"
                                 label="Status"
                                 placeholder="Alle"
                                 :options="$statusOptions"
                                 select="label:label|value:value" />

                <x-select.styled wire:model.live="productId"
                                 label="Katalogartikel"
                                 placeholder="Alle"
                                 :options="$products"
                                 select="label:name|value:id"
                                 searchable />

                <x-select.styled wire:model.live="categoryId"
                                 label="Kategorie"
                                 placeholder="Alle"
                                 :options="$categories"
                                 select="label:label|value:id"
                                 searchable />

                <x-select.styled wire:model.live="responsibleUserId"
                                 label="Interner Verantwortlicher"
                                 placeholder="Alle"
                                 :options="$responsibleUsers"
                                 select="label:name|value:id" />

                <x-select.styled wire:model.live="billingFilter"
                                 label="Abrechnung"
                                 placeholder="Alle"
                                 :options="[
                                     ['label' => 'Wird abgerechnet', 'value' => 'billable'],
                                     ['label' => 'Bewusst nicht abrechnen', 'value' => 'do_not_bill'],
                                     ['label' => 'Einmalige Leistungen', 'value' => 'once'],
                                 ]"
                                 select="label:label|value:value" />

                <div class="flex items-end gap-2">
                    <x-button color="secondary" outline sm wire:click="resetFilters">Filter zurücksetzen</x-button>

                    <x-button color="secondary"
                              outline
                              sm
                              icon="adjustments-horizontal"
                              wire:click="$set('showTableSettings', true)"
                              title="Spalten einrichten" />
                </div>
        </div>

            {{--
                Der Hinweis steht bewusst über der Tabelle und nicht in einer Spalte:
                eine geänderte Katalogposition betrifft mehrere Kunden gleichzeitig und
                geht in einer Zeile unter.
            --}}
            @if ($catalogChangeCount > 0)
                <button type="button"
                        wire:click="toggleCatalogFilter"
                        @class([
                            'mb-3 flex w-full items-center gap-3 rounded-[10px] border px-4 py-3 text-left transition',
                            'border-[color:var(--pill-warn-ink)] bg-[color:var(--pill-warn-bg)]' => $catalogFilter === 'changed',
                            'border-line bg-panel hover:border-line-strong' => $catalogFilter !== 'changed',
                        ])>
                    <x-icon name="arrow-path" class="h-4 w-4 flex-none text-[color:var(--pill-warn-ink)]" />

                    <span class="flex min-w-0 flex-col">
                        <span class="text-[12.5px] font-medium text-ink">
                            {{ trans_choice(
                                'Bei :count Leistung hat sich der Katalog seither geändert|Bei :count Leistungen hat sich der Katalog seither geändert',
                                $catalogChangeCount,
                                ['count' => $catalogChangeCount],
                            ) }}
                        </span>
                        <span class="text-[11.5px] text-ink-muted">
                            {{ $catalogFilter === 'changed'
                                ? 'Auswahl zeigt nur diese Leistungen — erneut klicken, um alle zu sehen.'
                                : 'Bestehende Leistungen werden nie automatisch angepasst. Klicken, um sie zu sehen.' }}
                        </span>
                    </span>
                </button>
            @endif

        <div class="mb-3.5 flex flex-wrap items-center justify-end gap-2.5">
            <span class="text-[11.5px] text-ink-faint">
                {{ trans_choice(':count Leistung|:count Leistungen', $services->total(), ['count' => number_format($services->total(), 0, ',', '.')]) }}
                in der Auswahl
            </span>
        </div>

        <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
            <div class="overflow-x-auto">
                {{--
                    Das Raster ist aus <div> gebaut. Ohne Rollen wäre es für
                    Screenreader eine Wand aus Text ohne Spaltenbezug.
                --}}
                <div role="table" aria-label="Kundenleistungen" style="min-width: {{ $mindestbreite }}px">
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

                    @forelse ($services as $leistung)
                        {{-- Die ganze Zeile ist ein Link, damit Mittelklick und Tastatur funktionieren. --}}
                        <div wire:key="leistung-{{ $leistung->id }}"
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
                                        <a href="{{ route('customer-services.show', [$leistung->customer, $leistung]) }}"
                                           wire:navigate
                                           class="flex min-w-0 items-center gap-[11px] after:absolute after:inset-0 focus-visible:outline-none">
                                            <x-avatar-initials :initials="$leistung->customer->initials()" size="sm" />

                                            <span class="truncate text-[12.5px] text-ink-base">
                                                {{ $leistung->customer->short_label ?: $leistung->customer->displayName() }}
                                            </span>
                                        </a>
                                        @break

                                    @case('name')
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate text-[13px] font-medium text-ink-base">{{ $leistung->name }}</span>

                                            @if ($leistung->billing_label)
                                                <span class="truncate text-[10.5px] text-ink-faint">{{ $leistung->billing_label }}</span>
                                            @endif
                                        </div>
                                        @break

                                    @case('product')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            @if ($leistung->product)
                                                {{ $leistung->product->name }}@if ($leistung->productVariant) · {{ $leistung->productVariant->name }}@endif
                                            @else
                                                <span class="text-ink-faint">Individuell</span>
                                            @endif
                                        </span>
                                        @break

                                    @case('interval')
                                        <span class="truncate text-[12px] text-ink-muted">{{ $leistung->billingInterval()->label() }}</span>
                                        @break

                                    @case('sales_price_cents')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink">{{ $leistung->salesPrice()->format() }}</span>
                                        @break

                                    @case('purchase_price_cents')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink-muted">{{ $leistung->purchasePrice()->format() }}</span>
                                        @break

                                    @case('monthly')
                                        <span class="truncate tabular text-right text-[12.5px] text-ink-muted">
                                            {{ $leistung->billingInterval()->isRecurring() ? $leistung->monthlyRevenue()->format() : '—' }}
                                        </span>
                                        @break

                                    @case('margin')
                                        <span @class([
                                            'truncate tabular text-right text-[12.5px]',
                                            'text-[color:var(--pill-bad-ink)]' => $leistung->margin()->isNegative(),
                                            'text-ink-base' => ! $leistung->margin()->isNegative(),
                                        ])>
                                            {{ $leistung->margin()->format() }}@if ($leistung->marginPercentage() !== null)<span class="text-ink-faint"> ({{ number_format($leistung->marginPercentage(), 1, ',', '.') }} %)</span>@endif
                                        </span>
                                        @break

                                    @case('status')
                                        <span>
                                            <x-status-pill :kind="$leistung->status->pillKind()" :label="$leistung->status->label()" />
                                        </span>
                                        @break

                                    @case('category')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            @if ($leistung->category && $leistung->subcategory)
                                                {{ $leistung->category->name }} <span class="text-ink-faint">›</span> {{ $leistung->subcategory->name }}
                                            @else
                                                {{ $leistung->category?->name ?? '—' }}
                                            @endif
                                        </span>
                                        @break

                                    @case('billing')
                                        <span>
                                            @if ($leistung->do_not_bill)
                                                <x-status-pill kind="warn" :label="$leistung->do_not_bill_reason?->label() ?? 'Nicht abrechnen'" />
                                            @elseif (! $leistung->billingInterval()->isRecurring())
                                                <x-status-pill kind="mute" label="Einmalig" />
                                            @elseif ($leistung->countsTowardsRevenue())
                                                <x-status-pill kind="ok" label="Wird abgerechnet" />
                                            @else
                                                <x-status-pill kind="mute" label="Ruht" />
                                            @endif
                                        </span>
                                        @break

                                    @case('responsible')
                                        <span class="truncate text-[12px] text-ink-muted">{{ $leistung->responsibleUser?->name ?? '—' }}</span>
                                        @break

                                    @case('service_start_date')
                                        <span class="truncate text-[12px] text-ink-muted">
                                            {{ $leistung->service_start_date?->format('d.m.Y') ?? '—' }}
                                        </span>
                                        @break
                                @endswitch
                            </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                            Keine Leistung passt zu Filter und Suche.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($services->hasPages())
                <div class="border-t border-line px-[17px] py-3">
                    {{ $services->links() }}
                </div>
            @endif
        </div>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
