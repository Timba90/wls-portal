<div>
    <x-page title="Projekttypen" subtitle="Frei definierbar — Webseite, Shop, Web-App und API sind nur Beispiele.">
        <x-slot:actions>
            <x-button icon="plus" wire:click="create">Projekttyp anlegen</x-button>
        </x-slot:actions>

        <x-card>
            <x-table :headers="[
                         ['index' => 'name', 'label' => 'Name'],
                         ['index' => 'short_label', 'label' => 'Kürzel', 'width' => '110px'],
                         ['index' => 'projects_count', 'label' => 'Projekte', 'width' => '120px'],
                         ['index' => 'is_active', 'label' => 'Status', 'width' => '130px'],
                         ['index' => 'action', 'label' => '', 'sortable' => false, 'width' => '80px'],
                     ]"
                     :rows="$projectTypes">
                @interact('column_name', $row)
                    <x-badge :color="$row->color" :text="$row->name" sm />
                @endinteract

                @interact('column_short_label', $row)
                    <span class="font-mono text-[11.5px] text-ink-muted">{{ $row->badge() }}</span>
                @endinteract

                @interact('column_is_active', $row)
                    <x-status-pill :kind="$row->is_active ? 'ok' : 'mute'"
                                   :label="$row->is_active ? 'Aktiv' : 'Inaktiv'" />
                @endinteract

                @interact('column_action', $row)
                    <x-button.circle color="secondary" outline icon="pencil" sm wire:click="edit({{ $row->id }})" />
                @endinteract

                <x-slot:empty>
                    <p class="py-6 text-center text-sm text-ink-muted">Noch keine Projekttypen angelegt.</p>
                </x-slot:empty>
            </x-table>
        </x-card>

        <x-modal wire="showForm"
                 id="projekttyp-formular"
                 :title="$editingTypeId ? 'Projekttyp bearbeiten' : 'Projekttyp anlegen'"
                 persistent>
            <form wire:submit="save" class="space-y-4" id="projekttyp-form">
                <x-input wire:model="name" label="Name" required />

                <x-input wire:model="short_label"
                         label="Kürzel"
                         maxlength="12"
                         hint="Ohne Angabe die ersten beiden Buchstaben des Namens." />

                <x-select.styled wire:model="color"
                                 label="Farbe"
                                 :options="$colorOptions"
                                 select="label:label|value:value" />

                <x-input wire:model="sort_order" type="number" min="0" max="9999" label="Sortierung" />

                <x-toggle wire:model="is_active"
                          label="Aktiv"
                          hint="Inaktive Typen stehen bei neuen Projekten nicht mehr zur Auswahl." />
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showForm', false)">Abbrechen</x-button>
                    <x-button type="submit" form="projekttyp-form">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('projekttyp-gespeichert', () => $tallstackui.toast().success('Projekttyp gespeichert').send());
        </script>
        @endscript
    </x-page>
</div>
