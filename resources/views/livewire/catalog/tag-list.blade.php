<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('products.index') }}"
               wire:navigate
               class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                &larr; Zurück zum Katalog
            </a>

            <h1 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">Tags</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Tags gelten übergreifend für Kunden, Ansprechpartner, Artikel und Kundenleistungen.
            </p>
        </div>

        <x-button icon="plus" wire:click="create">Tag anlegen</x-button>
    </div>

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
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Noch keine Tags angelegt.</p>
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
        $wire.on('tag-gespeichert', () => $tallstackui.toast().success('Tag gespeichert').send());
    </script>
    @endscript
</div>
