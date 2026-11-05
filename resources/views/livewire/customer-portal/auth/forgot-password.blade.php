<div class="flex flex-col gap-7">
    <div>
        <h2 class="text-[22px] font-semibold tracking-[-0.01em] text-ink">Passwort vergessen</h2>
        <p class="mt-1 text-[12.5px] text-ink-muted">
            Geben Sie die E-Mail-Adresse Ihres Portalzugangs ein.
        </p>
    </div>

    @if ($sent)
        <x-alert color="green">
            Wenn zu dieser Adresse ein Zugang besteht, ist der Link unterwegs. Bitte prüfen Sie Ihr Postfach.
        </x-alert>
    @else
        <form wire:submit="sendLink" class="flex flex-col gap-4">
            <x-input label="E-Mail-Adresse"
                     wire:model="email"
                     type="email"
                     autocomplete="username"
                     required
                     autofocus />

            <x-button type="submit" block loading="sendLink">Link anfordern</x-button>
        </form>
    @endif

    <a href="{{ route('customer-portal.login') }}"
       wire:navigate
       class="text-[12px] text-accent transition hover:underline">
        Zurück zur Anmeldung
    </a>
</div>
