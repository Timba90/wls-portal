<div>
        <x-page title="Kunden" subtitle="Alle Firmen- und Privatkunden mit ihren Kennzahlen.">
        <x-slot:actions>
            <x-button icon="plus" :href="route('customers.create')" wire:navigate>Kunde anlegen</x-button>
        </x-slot:actions>


        <x-card>
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="lg:w-72">
                    <x-input wire:model.live.debounce.300ms="search"
                             label="Suche"
                             placeholder="Nummer, Name, Kurzbezeichnung, Kürzel"
                             icon="magnifying-glass" />
                </div>

                <div class="lg:w-44">
                    <x-select.styled wire:model.live="status"
                                     label="Status"
                                     placeholder="Alle"
                                     :options="$statusOptions"
                                     select="label:label|value:value" />
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
                    <x-button color="secondary" outline wire:click="resetFilters">Filter zurücksetzen</x-button>

                    <x-button color="secondary"
                              outline
                              icon="adjustments-horizontal"
                              wire:click="$set('showTableSettings', true)"
                              title="Spalten einrichten" />
                </div>
            </div>

            <x-table :headers="$this->tableHeaders()"
                     :rows="$customers"
                     :sort="$sort"
                     paginate>
                @interact('column_customer_number', $row)
                    <a href="{{ route('customers.show', $row) }}"
                       wire:navigate
                       class="font-medium text-accent hover:underline">
                        {{ $row->customer_number }}
                    </a>
                @endinteract

                @interact('column_name', $row)
                    <a href="{{ route('customers.show', $row) }}"
                       wire:navigate
                       class="hover:underline">
                        {{ $row->displayName() }}
                    </a>
                @endinteract

                @interact('column_type', $row)
                    {{ $row->type->label() }}
                @endinteract

                @interact('column_status', $row)
                    <x-badge :color="$row->status->color()" :text="$row->status->label()" sm />
                @endinteract

                @interact('column_responsible', $row)
                    {{ $row->responsibleUser?->name ?? '—' }}
                @endinteract

                @interact('column_active_services_count', $row)
                    <span class="tabular-nums">{{ $row->active_services_count }}</span>
                @endinteract

                @interact('column_monthly_revenue', $row)
                    <span class="tabular-nums">{{ $row->monthlyRevenue()->format() }}</span>
                @endinteract

                @interact('column_yearly_revenue', $row)
                    <span class="tabular-nums">{{ $row->yearlyRevenue()->format() }}</span>
                @endinteract

                @interact('column_monthly_costs', $row)
                    <span class="tabular-nums">{{ $row->monthlyCosts()->format() }}</span>
                @endinteract

                @interact('column_margin', $row)
                    <span class="tabular-nums {{ $row->monthlyMargin()->isNegative() ? 'text-[color:var(--pill-bad-ink)]' : '' }}">
                        {{ $row->monthlyMargin()->format() }}
                    </span>
                @endinteract

                <x-slot:empty>
                    <p class="py-6 text-center text-sm text-ink-muted">
                        Keine Kunden gefunden.
                    </p>
                </x-slot:empty>
            </x-table>
        </x-card>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
