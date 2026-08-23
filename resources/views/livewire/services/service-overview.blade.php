<div>
    <x-page title="Leistungsübersicht" subtitle="Alle Kundenleistungen mit vereinbartem Preis, Kosten und Abrechnungsintervall.">

        <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-tile label="Monatsumsatz (Auswahl)" :value="$summe['monthlyRevenue']->format()" />
            <x-metric-tile label="Jahresumsatz (Auswahl)" :value="$summe['yearlyRevenue']->format()" />
            <x-metric-tile label="Kosten monatlich" :value="$summe['monthlyCosts']->format()" />
            <x-metric-tile label="Marge monatlich" :value="$summe['monthlyMargin']->format()" />
        </div>

        <x-card>
            <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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

                <x-select.styled wire:model.live="tagId"
                                 label="Tag"
                                 placeholder="Alle"
                                 :options="$tags"
                                 select="label:name|value:id" />

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
                    <x-button color="secondary" outline wire:click="resetFilters">Filter zurücksetzen</x-button>

                    <x-button color="secondary"
                              outline
                              icon="adjustments-horizontal"
                              wire:click="$set('showTableSettings', true)"
                              title="Spalten einrichten" />
                </div>
            </div>

            <p class="mb-3 text-sm text-ink-muted">
                {{ number_format($services->total(), 0, ',', '.') }} Leistungen in der Auswahl,
                davon {{ number_format($summe['count'], 0, ',', '.') }} in den Kennzahlen berücksichtigt.
            </p>

            <x-table :headers="$this->tableHeaders()" :rows="$services" :sort="$sort" paginate>
                @interact('column_customer', $row)
                    <a href="{{ route('customers.show', $row->customer) }}"
                       wire:navigate
                       class="text-accent hover:underline">
                        {{ $row->customer->short_label }}
                    </a>
                @endinteract

                @interact('column_name', $row)
                    <a href="{{ route('customer-services.show', [$row->customer, $row]) }}"
                       wire:navigate
                       class="font-medium text-accent hover:underline">
                        {{ $row->name }}
                    </a>
                @endinteract

                @interact('column_product', $row)
                    @if ($row->product)
                        {{ $row->product->name }}@if ($row->productVariant) · {{ $row->productVariant->name }}@endif
                    @else
                        <span class="text-ink-faint">Individuell</span>
                    @endif
                @endinteract

                @interact('column_category', $row)
                    @if ($row->category && $row->subcategory)
                        {{ $row->category->name }} <span class="text-ink-faint">&rarr;</span> {{ $row->subcategory->name }}
                    @else
                        {{ $row->category?->name ?? '—' }}
                    @endif
                @endinteract

                @interact('column_tags', $row)
                    <div class="flex flex-wrap gap-1">
                        @foreach ($row->tags as $tag)
                            <x-badge :color="$tag->color" :text="$tag->name" sm />
                        @endforeach
                    </div>
                @endinteract

                @interact('column_status', $row)
                    <x-badge :color="$row->status->color()" :text="$row->status->label()" sm />
                @endinteract

                @interact('column_purchase_price_cents', $row)
                    <span class="tabular-nums">{{ $row->purchasePrice()->format() }}</span>
                @endinteract

                @interact('column_sales_price_cents', $row)
                    <span class="tabular-nums">{{ $row->salesPrice()->format() }}</span>
                @endinteract

                @interact('column_margin', $row)
                    <span class="tabular-nums {{ $row->margin()->isNegative() ? 'text-[color:var(--pill-bad-ink)]' : '' }}">
                        {{ $row->margin()->format() }}
                        @if ($row->marginPercentage() !== null)
                            <span class="text-ink-faint">({{ number_format($row->marginPercentage(), 1, ',', '.') }} %)</span>
                        @endif
                    </span>
                @endinteract

                @interact('column_interval', $row)
                    {{ $row->billingInterval()->label() }}
                @endinteract

                @interact('column_monthly', $row)
                    <span class="tabular-nums">
                        {{ $row->billingInterval()->isRecurring() ? $row->monthlyRevenue()->format() : '—' }}
                    </span>
                @endinteract

                @interact('column_billing', $row)
                    @if ($row->do_not_bill)
                        <x-badge color="amber" :text="$row->do_not_bill_reason?->label() ?? 'Nicht abrechnen'" sm />
                    @elseif (! $row->billingInterval()->isRecurring())
                        <x-badge color="gray" text="Einmalig" sm />
                    @elseif ($row->countsTowardsRevenue())
                        <x-badge color="green" text="Wird abgerechnet" sm />
                    @else
                        <x-badge color="gray" text="Ruht" sm />
                    @endif
                @endinteract

                @interact('column_responsible', $row)
                    {{ $row->responsibleUser?->name ?? '—' }}
                @endinteract

                @interact('column_service_start_date', $row)
                    {{ $row->service_start_date?->format('d.m.Y') ?? '—' }}
                @endinteract

                <x-slot:empty>
                    <p class="py-6 text-center text-sm text-ink-muted">
                        Keine Leistungen gefunden.
                    </p>
                </x-slot:empty>
            </x-table>
        </x-card>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
