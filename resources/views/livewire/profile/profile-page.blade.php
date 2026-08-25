<div>
    <x-page title="Profil" subtitle="Ihre Stammdaten und Ihr Passwort." class="flex flex-col gap-4">

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Stammdaten</h2>
            </x-slot:header>

            <form wire:submit="updateProfile" class="max-w-lg space-y-4">
                <x-input label="Name" wire:model="name" required />
                <x-input label="E-Mail-Adresse" type="email" wire:model="email" required />

                <div class="flex justify-end">
                    <x-button type="submit" wire:loading.attr="disabled">Speichern</x-button>
                </div>
            </form>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Passwort ändern</h2>
            </x-slot:header>

            <form wire:submit="updatePassword" class="max-w-lg space-y-4">
                <x-password label="Aktuelles Passwort"
                            wire:model="current_password"
                            autocomplete="current-password"
                            required />

                <x-password label="Neues Passwort"
                            wire:model="password"
                            autocomplete="new-password"
                            :rules="config('portal.password_rules')"
                            required />

                <x-password label="Neues Passwort bestätigen"
                            wire:model="password_confirmation"
                            autocomplete="new-password"
                            required />

                <div class="flex justify-end">
                    <x-button type="submit" wire:loading.attr="disabled">Passwort ändern</x-button>
                </div>
            </form>
        </x-card>

        @script
        <script>
            $wire.on('profil-gespeichert', () => $tsui.interaction('toast').success('Profil gespeichert').send());
            $wire.on('passwort-gespeichert', () => $tsui.interaction('toast').success('Passwort geändert').send());
        </script>
        @endscript
    </x-page>
</div>
