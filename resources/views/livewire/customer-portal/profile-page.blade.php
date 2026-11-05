<div>
    <x-page title="Profil & Passwort" :subtitle="$portalUser->email">
        <div class="grid max-w-[960px] items-start gap-3.5 lg:grid-cols-2">
            <x-panel title="Profil">
                <form wire:submit="saveProfile" class="flex flex-col gap-4">
                    <x-input label="Name" wire:model="name" autocomplete="name" required />
                    <x-input label="E-Mail-Adresse" :value="$portalUser->email" disabled
                             hint="Für eine Änderung sprechen Sie uns bitte an." />
                    <x-input label="Kundenkonto" :value="$customer->displayName().' · '.$customer->customer_number" disabled />

                    <div class="flex items-center gap-3">
                        <x-button type="submit" loading="saveProfile">Speichern</x-button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="Passwort ändern">
                <form wire:submit="changePassword" class="flex flex-col gap-4">
                    <x-password label="Aktuelles Passwort" wire:model="currentPassword" autocomplete="current-password" required />
                    <x-password label="Neues Passwort" wire:model="password" autocomplete="new-password"
                                :rules="config('portal.password_rules')" required />
                    <x-password label="Neues Passwort wiederholen" wire:model="password_confirmation" autocomplete="new-password" required />

                    <div class="flex items-center gap-3">
                        <x-button type="submit" loading="changePassword">Passwort ändern</x-button>
                    </div>
                </form>
            </x-panel>
        </div>

        @script
        <script>
            $wire.on('profil-gespeichert', () => $tsui.interaction('toast').success('Profil gespeichert').send());
            $wire.on('passwort-geaendert', () => $tsui.interaction('toast').success('Passwort geändert').send());
        </script>
        @endscript
    </x-page>
</div>
