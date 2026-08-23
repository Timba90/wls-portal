@props([
    'components',
    'addAction' => 'addComponent',
    'removeAction' => 'removeComponent',
    'moveAction' => null,
    'statePath' => 'components',
])

{{--
    Editor fuer Leistungsbestandteile.

    Wird von Katalogartikeln, Artikelvarianten und Kundenleistungen gemeinsam
    genutzt; die Struktur ist ueberall identisch.
--}}
<div class="space-y-3">
    @foreach ($components as $index => $component)
        <div class="rounded-md border border-gray-200 p-3 dark:border-dark-600"
             wire:key="{{ $statePath }}-{{ $index }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @if ($moveAction)
                    <div class="flex gap-1 sm:flex-col sm:pb-2">
                        <button type="button"
                                class="cursor-pointer text-gray-400 transition hover:text-gray-700 disabled:cursor-default disabled:opacity-30 dark:hover:text-gray-200"
                                wire:click="{{ $moveAction }}({{ $index }}, -1)"
                                @disabled($index === 0)
                                title="Nach oben">
                            <x-icon name="chevron-up" class="h-4 w-4" />
                        </button>
                        <button type="button"
                                class="cursor-pointer text-gray-400 transition hover:text-gray-700 disabled:cursor-default disabled:opacity-30 dark:hover:text-gray-200"
                                wire:click="{{ $moveAction }}({{ $index }}, 1)"
                                @disabled($index === count($components) - 1)
                                title="Nach unten">
                            <x-icon name="chevron-down" class="h-4 w-4" />
                        </button>
                    </div>
                @endif

                <div class="flex-1">
                    <x-input wire:model="{{ $statePath }}.{{ $index }}.title" label="Titel" />
                </div>

                <div class="sm:w-36">
                    <x-input wire:model="{{ $statePath }}.{{ $index }}.purchase_price"
                             label="Einkauf"
                             placeholder="optional"
                             suffix="€" />
                </div>

                <div class="sm:w-36">
                    <x-input wire:model="{{ $statePath }}.{{ $index }}.sales_price"
                             label="Verkauf"
                             placeholder="optional"
                             suffix="€" />
                </div>

                <div class="sm:pb-2">
                    <x-button.circle color="red" outline icon="trash" sm
                                     wire:click="{{ $removeAction }}({{ $index }})" title="Entfernen" />
                </div>
            </div>

            <div class="mt-3">
                <x-textarea wire:model="{{ $statePath }}.{{ $index }}.description"
                            label="Beschreibung"
                            rows="2"
                            placeholder="optional" />
            </div>
        </div>
    @endforeach

    <x-button color="secondary" outline sm icon="plus" wire:click="{{ $addAction }}">
        Leistungsbestandteil hinzufügen
    </x-button>
</div>
