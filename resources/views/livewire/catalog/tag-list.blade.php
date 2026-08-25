<div>
        <x-page title="Tags" subtitle="Tags gelten übergreifend für Kunden, Ansprechpartner, Artikel und Kundenleistungen.">
        <x-slot:actions>
            <x-button icon="plus" wire:click="create">Tag anlegen</x-button>
        </x-slot:actions>


        <x-card>
            <x-table :headers="[
                         ['index' => 'name', 'label' => 'Name'],
                         ['index' => 'customers_count', 'label' => 'Kunden', 'width' => '120px'],
                         ['index' => 'contacts_count', 'label' => 'Ansprechpartner', 'width' => '160px'],
                         ['index' => 'products_count', 'label' => 'Artikel', 'width' => '120px'],
                         ['index' => 'action', 'label' => '', 'sortable' => false, 'width' => '80px'],
                     ]"
                     :rows="$tags">
                @interact('column_name', $row)
                    <x-badge :color="$row->color" :text="$row->name" sm />
                @endinteract

                @interact('column_action', $row)
                    <x-button.circle color="secondary" outline icon="pencil" sm wire:click="edit({{ $row->id }})" />
                @endinteract

                <x-slot:empty>
                    <p class="py-6 text-center text-sm text-ink-muted">Noch keine Tags angelegt.</p>
                </x-slot:empty>
            </x-table>
        </x-card>

        <x-modal wire="showForm" id="tag-formular" :title="$editingTagId ? 'Tag bearbeiten' : 'Tag anlegen'" persistent>
            <form wire:submit="save" class="space-y-4" id="tag-form">
                <x-input wire:model="name" label="Name" required />

                <x-select.styled wire:model="color"
                                 label="Farbe"
                                 :options="$colorOptions"
                                 select="label:label|value:value" />
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showForm', false)">Abbrechen</x-button>
                    <x-button type="submit" form="tag-form">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('tag-gespeichert', () => $tsui.interaction('toast').success('Tag gespeichert').send());
        </script>
        @endscript
    </x-page>
</div>
