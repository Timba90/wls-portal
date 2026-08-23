@props(['columns'])

{{--
    Spalteneinstellungen einer Listentabelle.

    Die Konfiguration gilt global fuer alle Benutzer; die aufrufende
    Livewire-Komponente nutzt dafuer den Trait WithConfigurableTable.
--}}
<x-modal wire:model="showTableSettings" title="Spalten einrichten" size="lg">
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        Diese Einstellung gilt für alle Benutzer.
    </p>

    <div class="divide-y divide-gray-200 dark:divide-dark-600">
        @foreach ($columns as $index => $column)
            <div class="flex items-center gap-3 py-2" wire:key="column-{{ $column['key'] }}">
                <div class="flex flex-col">
                    <button type="button"
                            class="cursor-pointer text-gray-400 transition hover:text-gray-700 disabled:cursor-default disabled:opacity-30 dark:hover:text-gray-200"
                            wire:click="moveColumn('{{ $column['key'] }}', -1)"
                            @disabled($index === 0)
                            title="Nach oben">
                        <x-icon name="chevron-up" class="h-4 w-4" />
                    </button>
                    <button type="button"
                            class="cursor-pointer text-gray-400 transition hover:text-gray-700 disabled:cursor-default disabled:opacity-30 dark:hover:text-gray-200"
                            wire:click="moveColumn('{{ $column['key'] }}', 1)"
                            @disabled($index === count($columns) - 1)
                            title="Nach unten">
                        <x-icon name="chevron-down" class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex-1">
                    <span class="text-sm text-gray-800 dark:text-gray-100">{{ $column['label'] }}</span>
                    @if ($column['fixed'])
                        <x-badge color="gray" text="Immer sichtbar" sm class="ml-2" />
                    @endif
                </div>

                <div class="w-32">
                    <x-input type="number"
                             min="60"
                             max="600"
                             step="10"
                             placeholder="Breite"
                             :value="$column['width']"
                             wire:change="setColumnWidth('{{ $column['key'] }}', $event.target.value ? parseInt($event.target.value) : null)" />
                </div>

                <x-toggle sm
                          :checked="$column['visible']"
                          :disabled="$column['fixed']"
                          wire:click="toggleColumn('{{ $column['key'] }}')" />
            </div>
        @endforeach
    </div>

    <x-slot:footer>
        <div class="flex justify-between">
            <x-button color="secondary" outline wire:click="resetTableConfiguration">
                Auf Standard zurücksetzen
            </x-button>

            <x-button wire:click="$set('showTableSettings', false)">Fertig</x-button>
        </div>
    </x-slot:footer>
</x-modal>
