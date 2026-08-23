<div>
    @if ($definitions->isEmpty())
        <p class="py-4 text-sm text-gray-500 dark:text-gray-400">
            Für diesen Bereich sind keine benutzerdefinierten Felder eingerichtet.
        </p>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($definitions as $definition)
                <div wire:key="feld-{{ $definition->id }}"
                     @class(['md:col-span-2' => in_array($definition->type->value, ['textarea', 'multiselect'], true)])>
                    @switch ($definition->type->value)
                        @case ('textarea')
                            <x-textarea wire:model="values.{{ $definition->key }}"
                                        :label="$definition->name"
                                        rows="3"
                                        :required="$definition->is_required"
                                        :disabled="$readOnly" />
                            @break

                        @case ('number')
                            <x-input wire:model="values.{{ $definition->key }}"
                                     type="number"
                                     step="any"
                                     :label="$definition->name"
                                     :required="$definition->is_required"
                                     :disabled="$readOnly" />
                            @break

                        @case ('date')
                            <x-date wire:model="values.{{ $definition->key }}"
                                    :label="$definition->name"
                                    format="DD.MM.YYYY"
                                    :required="$definition->is_required" />
                            @break

                        @case ('boolean')
                            <x-toggle wire:model="values.{{ $definition->key }}"
                                      :label="$definition->name"
                                      :disabled="$readOnly" />
                            @break

                        @case ('select')
                            <x-select.styled wire:model="values.{{ $definition->key }}"
                                             :label="$definition->name"
                                             placeholder="Bitte wählen"
                                             :options="$definition->optionList()"
                                             select="label:label|value:value"
                                             :required="$definition->is_required"
                                             :disabled="$readOnly" />
                            @break

                        @case ('multiselect')
                            <x-select.styled wire:model="values.{{ $definition->key }}"
                                             :label="$definition->name"
                                             placeholder="Bitte wählen"
                                             :options="$definition->optionList()"
                                             select="label:label|value:value"
                                             multiple
                                             :required="$definition->is_required"
                                             :disabled="$readOnly" />
                            @break

                        @case ('email')
                            <x-input wire:model="values.{{ $definition->key }}"
                                     type="email"
                                     :label="$definition->name"
                                     :required="$definition->is_required"
                                     :disabled="$readOnly" />
                            @break

                        @case ('url')
                            <x-input wire:model="values.{{ $definition->key }}"
                                     type="url"
                                     :label="$definition->name"
                                     placeholder="https://"
                                     :required="$definition->is_required"
                                     :disabled="$readOnly" />
                            @break

                        @default
                            <x-input wire:model="values.{{ $definition->key }}"
                                     :label="$definition->name"
                                     :required="$definition->is_required"
                                     :disabled="$readOnly" />
                    @endswitch
                </div>
            @endforeach
        </div>

        @unless ($readOnly)
            <div class="mt-4 flex justify-end">
                <x-button sm wire:click="save" wire:loading.attr="disabled">Felder speichern</x-button>
            </div>
        @endunless
    @endif

    @script
    <script>
        $wire.on('felder-gespeichert', () => $tallstackui.toast().success('Felder gespeichert').send());
    </script>
    @endscript
</div>
