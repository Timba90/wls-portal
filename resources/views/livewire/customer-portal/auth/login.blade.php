<div class="flex flex-col gap-7">
    <div>
        <h2 class="text-[22px] font-semibold tracking-[-0.01em] text-ink">Anmelden</h2>
        <p class="mt-1 text-[12.5px] text-ink-muted">
            Kundenportal von {{ config('portal.brand.name') }}.
        </p>
    </div>

    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if (request()->has('abgelaufen'))
        <x-alert color="amber">
            Ihre Sitzung wurde wegen Inaktivität beendet. Bitte melden Sie sich erneut an.
        </x-alert>
    @endif

    <form wire:submit="login" class="flex flex-col gap-4">
        <x-input label="E-Mail-Adresse"
                 wire:model="email"
                 type="email"
                 autocomplete="username"
                 placeholder="name@firma.de"
                 required
                 autofocus />

        <x-password label="Passwort"
                    wire:model="password"
                    autocomplete="current-password"
                    placeholder="••••••••••"
                    required />

        <div class="flex items-center justify-between gap-4">
            <x-toggle label="Angemeldet bleiben" wire:model="remember" sm />

            <a href="{{ route('customer-portal.password.request') }}"
               wire:navigate
               class="text-[12px] text-accent transition hover:underline">
                Passwort vergessen?
            </a>
        </div>

        <x-button type="submit" block loading="login">Anmelden</x-button>
    </form>

    <div class="flex items-center justify-between gap-4 border-t border-line pt-5 text-[11px] text-ink-faint">
        <span>Noch kein Zugang?</span>
        <span>Ihr Ansprechpartner richtet ihn gern für Sie ein.</span>
    </div>
</div>
