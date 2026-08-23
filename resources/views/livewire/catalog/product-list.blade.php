<div>
        <x-page title="Artikel / Leistungen" subtitle="Der zentrale Katalog mit Standardpreisen und Abrechnungsintervallen.">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
            <x-button color="secondary" outline :href="route('categories.index')" wire:navigate>Kategorien</x-button>
            <x-button color="secondary" outline :href="route('tags.index')" wire:navigate>Tags</x-button>
            <x-button icon="plus" :href="route('products.create')" wire:navigate>Artikel anlegen</x-button>
        </div>
        </x-slot:actions>


        <x-card>
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="lg:w-72">
                    <x-input wire:model.live.debounce.300ms="search"
                             label="Suche"
                             placeholder="Name, interne Bezeichnung oder Beschreibung"
                             icon="magnifying-glass" />
                </div>

                <div class="lg:w-44">
                    <x-select.styled wire:model.live="status"
                                     label="Status"
                                     placeholder="Alle"
                                     :options="$statusOptions"
                                     select="label:label|value:value" />
                </div>

                <div class="lg:w-64">
                    <x-select.styled wire:model.live="categoryId"
                                     label="Kategorie"
                                     placeholder="Alle"
                                     :options="$categories"
                                     select="label:label|value:id"
                                     searchable />
                </div>

                <div class="lg:w-48">
                    <x-select.styled wire:model.live="tagId"
                                     label="Tag"
                                     placeholder="Alle"
                                     :options="$tags"
                                     select="label:name|value:id" />
                </div>

                <div class="flex gap-2 lg:ml-auto">
                    <x-button color="secondary" outline wire:click="resetFilters">Filter zurücksetzen</x-button>

                    <x-button color="secondary"
                              outline
                              icon="adjustments-horizontal"
                              wire:click="$set('showTableSettings', true)"
                              title="Spalten einrichten" />
                </div>
            </div>

            <x-table :headers="$this->tableHeaders()" :rows="$products" :sort="$sort" paginate>
                @interact('column_name', $row)
                    <a href="{{ route('products.show', $row) }}"
                       wire:navigate
                       class="font-medium text-accent hover:underline">
                        {{ $row->name }}
                    </a>
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

                @interact('column_variants_count', $row)
                    <span class="tabular-nums">{{ $row->variants_count }}</span>
                @endinteract

                @interact('column_default_purchase_price_cents', $row)
                    <span class="tabular-nums">{{ $row->defaultPurchasePrice()->format() }}</span>
                @endinteract

                @interact('column_default_sales_price_cents', $row)
                    <span class="tabular-nums">{{ $row->defaultSalesPrice()->format() }}</span>
                @endinteract

                @interact('column_margin', $row)
                    <span class="tabular-nums {{ $row->defaultMargin()->isNegative() ? 'text-[color:var(--pill-bad-ink)]' : '' }}">
                        {{ $row->defaultMargin()->format() }}
                        @if ($row->defaultMarginPercentage() !== null)
                            <span class="text-ink-faint">({{ number_format($row->defaultMarginPercentage(), 1, ',', '.') }} %)</span>
                        @endif
                    </span>
                @endinteract

                @interact('column_interval', $row)
                    {{ $row->defaultBillingInterval()->label() }}
                @endinteract

                @interact('column_status', $row)
                    <x-badge :color="$row->status->color()" :text="$row->status->label()" sm />
                @endinteract

                <x-slot:empty>
                    <p class="py-6 text-center text-sm text-ink-muted">
                        Keine Artikel gefunden.
                    </p>
                </x-slot:empty>
            </x-table>
        </x-card>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
