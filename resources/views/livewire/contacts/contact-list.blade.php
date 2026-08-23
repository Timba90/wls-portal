<div>
        <x-page title="Ansprechpartner" subtitle="Ansprechpartner der Firmenkunden mit Rollen und Kontaktdaten.">
        <x-slot:actions>
            <div class="flex gap-2">
            <x-button color="secondary" outline :href="route('contact-roles.index')" wire:navigate>
                Rollen verwalten
            </x-button>

            <x-button icon="plus" :href="route('contacts.create')" wire:navigate>
                Ansprechpartner anlegen
            </x-button>
        </div>
        </x-slot:actions>


        <x-card>
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
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
                    <x-button color="secondary" outline wire:click="resetFilters">Filter zurücksetzen</x-button>

                    <x-button color="secondary"
                              outline
                              icon="adjustments-horizontal"
                              wire:click="$set('showTableSettings', true)"
                              title="Spalten einrichten" />
                </div>
            </div>

            <x-table :headers="$this->tableHeaders()" :rows="$contacts" :sort="$sort" paginate>
                @interact('column_name', $row)
                    <a href="{{ route('contacts.show', $row) }}"
                       wire:navigate
                       class="font-medium text-accent hover:underline">
                        {{ $row->listName() }}
                    </a>
                @endinteract

                @interact('column_email', $row)
                    {{ $row->primaryEmailAddress()?->email ?? '—' }}
                @endinteract

                @interact('column_phone', $row)
                    {{ $row->primaryPhoneNumber()?->number ?? '—' }}
                @endinteract

                @interact('column_customers', $row)
                    <div class="flex flex-wrap gap-1">
                        @foreach ($row->assignments as $assignment)
                            <a href="{{ route('customers.show', $assignment->customer) }}"
                               wire:navigate
                               class="text-accent hover:underline">
                                {{ $assignment->customer->short_label }}
                            </a>
                        @endforeach
                    </div>
                @endinteract

                @interact('column_roles', $row)
                    <div class="flex flex-wrap gap-1">
                        @foreach ($row->assignments->flatMap->roles->unique('id') as $role)
                            <x-badge color="gray" :text="$role->name" sm />
                        @endforeach
                    </div>
                @endinteract

                @interact('column_preferred_contact_method', $row)
                    {{ $row->preferred_contact_method?->label() ?? '—' }}
                @endinteract

                @interact('column_status', $row)
                    @if ($row->isArchived())
                        <x-badge color="gray" text="Archiviert" sm />
                    @else
                        <x-badge color="green" text="Aktiv" sm />
                    @endif
                @endinteract

                <x-slot:empty>
                    <p class="py-6 text-center text-sm text-ink-muted">
                        Keine Ansprechpartner gefunden.
                    </p>
                </x-slot:empty>
            </x-table>
        </x-card>

        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
