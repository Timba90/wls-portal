<div>
        <x-page title="Benutzerdefinierte Felder" subtitle="Zusätzliche Felder für Kunden, Artikel und Kundenleistungen.">
        <x-slot:actions>
            <x-button icon="plus" wire:click="create">Feld anlegen</x-button>
        </x-slot:actions>


        <x-card>
            <div class="mb-4 max-w-xs">
                <x-select.styled wire:model.live="entityFilter"
                                 label="Bereich"
                                 placeholder="Alle"
                                 :options="$entityOptions"
                                 select="label:label|value:value" />
            </div>

            <x-table :headers="[
                         ['index' => 'name', 'label' => 'Name'],
                         ['index' => 'key', 'label' => 'Schlüssel'],
                         ['index' => 'entity_type', 'label' => 'Bereich'],
                         ['index' => 'type', 'label' => 'Typ'],
                         ['index' => 'is_required', 'label' => 'Pflicht', 'width' => '100px'],
                         ['index' => 'values_count', 'label' => 'Belegt', 'width' => '100px'],
                         ['index' => 'is_active', 'label' => 'Status', 'width' => '120px'],
                         ['index' => 'action', 'label' => '', 'sortable' => false, 'width' => '80px'],
                     ]"
                     :rows="$definitions">
                @interact('column_entity_type', $row)
                    {{ $row->entity_type->label() }}
                @endinteract

                @interact('column_type', $row)
                    {{ $row->type->label() }}
                @endinteract

                @interact('column_is_required', $row)
                    {{ $row->is_required ? 'Ja' : 'Nein' }}
                @endinteract

                @interact('column_is_active', $row)
                    @if ($row->is_active)
                        <x-badge color="green" text="Aktiv" sm />
                    @else
                        <x-badge color="gray" text="Inaktiv" sm />
                    @endif
                @endinteract

                @interact('column_action', $row)
                    <x-button.circle color="secondary" outline icon="pencil" sm wire:click="edit({{ $row->id }})" />
                @endinteract

                <x-slot:empty>
                    <p class="py-6 text-center text-sm text-ink-muted">
                        Noch keine benutzerdefinierten Felder angelegt.
                    </p>
                </x-slot:empty>
            </x-table>
        </x-card>

        <x-modal wire="showForm"
                 id="feld-formular"
                 :title="$editingDefinitionId ? 'Feld bearbeiten' : 'Feld anlegen'"
                 persistent>
            <div class="space-y-4">
                <x-select.styled wire:model="entity_type"
                                 label="Bereich"
                                 :options="$entityOptions"
                                 select="label:label|value:value"
                                 :disabled="(bool) $editingDefinitionId"
                                 required />

                <x-input wire:model.live.debounce.400ms="name" label="Name" required />

                <x-input wire:model="key"
                         label="Schlüssel"
                         hint="Technischer Schlüssel, nur Kleinbuchstaben, Zahlen und Unterstriche."
                         :disabled="(bool) $editingDefinitionId"
                         required />

                <x-select.styled wire:model.live="type"
                                 label="Typ"
                                 :options="$typeOptions"
                                 select="label:label|value:value"
                                 required />

                @if ($this->requiresOptions())
                    <x-textarea wire:model="optionsInput"
                                label="Optionen"
                                rows="4"
                                hint="Eine Option je Zeile." />
                @endif

                <x-input wire:model="default_value" label="Standardwert" placeholder="optional" />
                <x-input wire:model="sort_order" type="number" min="0" max="9999" label="Sortierung" />

                <div class="flex flex-wrap gap-4">
                    <x-toggle wire:model="is_required" label="Pflichtfeld" sm />
                    <x-toggle wire:model="is_active" label="Aktiv" sm />
                </div>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showForm', false)">Abbrechen</x-button>
                    <x-button wire:click="save">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('feld-gespeichert', () => $tsui.interaction('toast').success('Feld gespeichert').send());
        </script>
        @endscript
    </x-page>
</div>
