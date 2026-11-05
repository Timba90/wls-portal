<div class="flex flex-col gap-7">
    <div>
        <h2 class="text-[22px] font-semibold tracking-[-0.01em] text-ink">Passwort festlegen</h2>
        <p class="mt-1 text-[12.5px] text-ink-muted">
            Mindestens 12 Zeichen mit Groß- und Kleinbuchstaben, Zahl und Sonderzeichen.
        </p>
    </div>

    <form wire:submit="resetPassword" class="flex flex-col gap-4">
        <x-input label="E-Mail-Adresse"
                 wire:model="email"
                 type="email"
                 autocomplete="username"
                 required />

        <x-password label="Neues Passwort"
                    wire:model="password"
                    autocomplete="new-password"
                    :rules="config('portal.password_rules')"
                    required />

        <x-password label="Passwort wiederholen"
                    wire:model="password_confirmation"
                    autocomplete="new-password"
                    required />

        <x-button type="submit" block loading="resetPassword">Passwort speichern</x-button>
    </form>
</div>
