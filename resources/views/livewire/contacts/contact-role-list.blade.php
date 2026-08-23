<div>
        <x-page title="Ansprechpartnerrollen" subtitle="Rollen sind frei definierbar und können je Kundenzuordnung mehrfach vergeben werden.">
        <x-slot:actions>
            <x-button icon="plus" wire:click="create">Rolle anlegen</x-button>
        </x-slot:actions>


        <x-card>
            <x-table :headers="[
                         ['index' => 'name', 'label' => 'Name'],
                         ['index' => 'description', 'label' => 'Beschreibung'],
                         ['index' => 'sort_order', 'label' => 'Sortierung', 'width' => '120px'],
                         ['index' => 'assignments_count', 'label' => 'Zuordnungen', 'width' => '140px'],
                         ['index' => 'is_active', 'label' => 'Status', 'width' => '120px'],
                         ['index' => 'action', 'label' => '', 'sortable' => false, 'width' => '80px'],
                     ]"
                     :rows="$roles">
                @interact('column_description', $row)
                    {{ $row->description ?? '—' }}
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
            </x-table>
        </x-card>

        <x-modal wire="showForm" id="rollen-formular" :title="$editingRoleId ? 'Rolle bearbeiten' : 'Rolle anlegen'" persistent>
            <form wire:submit="save" class="space-y-4" id="role-form">
                <x-input wire:model="name" label="Name" required />
                <x-input wire:model="description" label="Beschreibung" />
                <x-input wire:model="sort_order" type="number" min="0" max="9999" label="Sortierung" />
                <x-toggle wire:model="is_active" label="Aktiv" />
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showForm', false)">Abbrechen</x-button>
                    <x-button type="submit" form="role-form">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('rolle-gespeichert', () => $tallstackui.toast().success('Rolle gespeichert').send());
        </script>
        @endscript
    </x-page>
</div>
