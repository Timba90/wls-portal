<div>
        <x-page title="Benutzer" subtitle="Interne Benutzer der Verwaltungskonsole. Alle Benutzer haben dieselben Rechte.">
        <x-slot:actions>
            <x-button wire:click="create" icon="plus">Benutzer anlegen</x-button>
        </x-slot:actions>


        <x-card>
            <div class="mb-4 max-w-sm">
                <x-input wire:model.live.debounce.300ms="search"
                         placeholder="Name oder E-Mail-Adresse suchen"
                         icon="magnifying-glass" />
            </div>

            <x-table :headers="[
                         ['index' => 'name', 'label' => 'Name'],
                         ['index' => 'email', 'label' => 'E-Mail-Adresse'],
                         ['index' => 'two_factor', 'label' => '2FA'],
                         ['index' => 'created_at', 'label' => 'Angelegt'],
                         ['index' => 'action', 'label' => '', 'sortable' => false],
                     ]"
                     :rows="$users"
                     paginate>
                @interact('column_two_factor', $row)
                    @if ($row->hasTwoFactorEnabled())
                        <x-badge color="green" text="Aktiv" sm />
                    @else
                        <x-badge color="gray" text="Nicht aktiv" sm />
                    @endif
                @endinteract

                @interact('column_created_at', $row)
                    {{ $row->created_at->translatedFormat('d.m.Y') }}
                @endinteract

                @interact('column_action', $row)
                    <x-button.circle color="secondary" outline icon="pencil" sm wire:click="edit({{ $row->id }})" />
                @endinteract
            </x-table>
        </x-card>

        <x-modal wire="showForm" id="benutzer-formular" :title="$editingUserId ? 'Benutzer bearbeiten' : 'Benutzer anlegen'" persistent>
            <form wire:submit="save" class="space-y-4" id="user-form">
                <x-input label="Name" wire:model="name" required />
                <x-input label="E-Mail-Adresse" type="email" wire:model="email" required />

                <x-password label="Passwort"
                            wire:model="password"
                            autocomplete="new-password"
                            :rules="config('portal.password_rules')"
                            generator
                            :hint="$editingUserId ? 'Leer lassen, um das Passwort unverändert zu lassen.' : null"
                            :required="! $editingUserId" />

                <x-password label="Passwort bestätigen"
                            wire:model="password_confirmation"
                            autocomplete="new-password"
                            :required="! $editingUserId" />
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showForm', false)">Abbrechen</x-button>
                    <x-button type="submit" form="user-form" wire:loading.attr="disabled">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('benutzer-gespeichert', () => $tsui.interaction('toast').success('Benutzer gespeichert').send());
        </script>
        @endscript
    </x-page>
</div>
