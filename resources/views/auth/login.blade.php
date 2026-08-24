<x-layouts.guest title="Anmelden">
    {{--
        Systemstatus-Panel der Markenspalte. Nutzt die Marken-Tokens, weil die
        Spalte konstant dunkel bleibt und nicht dem Farbschema folgt.
    --}}
    <x-slot:aside>
        <div class="max-w-[430px] rounded-[10px] border border-brand-line bg-brand-panel/90 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <span class="text-[10px] font-semibold uppercase tracking-[0.11em] text-brand-dim">
                    Systemstatus
                </span>
                <span class="inline-flex items-center gap-[7px] text-[11px] font-medium text-brand-accent">
                    <span class="h-[5px] w-[5px] rounded-full bg-brand-accent"></span>
                    Alle Dienste in Betrieb
                </span>
            </div>

            <dl class="flex flex-col gap-2">
                @foreach ([
                    'Anwendung' => 'erreichbar',
                    'Warteschlangen' => 'laufen',
                    'Letzte Sicherung' => 'heute',
                ] as $label => $value)
                    <div class="flex items-center justify-between gap-4 text-[11.5px]">
                        <dt class="text-brand-muted">{{ $label }}</dt>
                        <dd class="font-mono tabular-nums text-brand-text">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </x-slot:aside>

    <div>
        <h2 class="text-[22px] font-semibold tracking-[-0.01em] text-ink">Anmelden</h2>
        <p class="mt-1 text-[12.5px] text-ink-muted">
            Zugang für das Team von {{ config('portal.brand.name') }}.
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

    <x-errors title="Anmeldung nicht möglich" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
        @csrf

        <x-input label="Dienst-E-Mail"
                 name="email"
                 type="email"
                 autocomplete="username"
                 placeholder="vorname.name@weblab.studio"
                 required
                 autofocus
                 :value="old('email')" />

        <x-password label="Passwort"
                    name="password"
                    autocomplete="current-password"
                    placeholder="••••••••••"
                    required />

        <div class="flex items-center justify-between gap-4">
            <x-toggle label="Angemeldet bleiben" name="remember" value="1" sm />

            <a href="{{ route('password.request') }}"
               class="text-[12px] text-accent transition hover:underline">
                Passwort vergessen?
            </a>
        </div>

        <x-button type="submit" block>Anmelden</x-button>

        <p class="text-[11px] leading-relaxed text-ink-faint">
            Ist die Zwei-Faktor-Authentifizierung aktiv, folgt nach dem Passwort der
            Bestätigungscode aus der Authenticator-App.
        </p>
    </form>

    <div class="flex items-center justify-between gap-4 border-t border-line pt-5 text-[11px] text-ink-faint">
        <span>Zugang nur für Teammitglieder.</span>
        <span>Benutzer legt ein bestehendes Teammitglied an.</span>
    </div>
</x-layouts.guest>
