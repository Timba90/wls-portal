<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Sicherheit</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Zwei-Faktor-Authentifizierung und aktive Sitzungen.
        </p>
    </div>

    @if (session('warning'))
        <x-alert color="amber">{{ session('warning') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Zwei-Faktor-Authentifizierung</h2>

                @if (auth()->user()->hasTwoFactorEnabled())
                    <x-badge color="green" text="Aktiv" sm />
                @else
                    <x-badge color="gray" text="Nicht aktiv" sm />
                @endif
            </div>
        </x-slot:header>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Ergänzt die Anmeldung um einen zeitbasierten Einmalcode (TOTP) aus einer
                Authenticator-App.
                @if (config('auth.two_factor_required'))
                    <strong>Sie ist in dieser Installation verpflichtend.</strong>
                @endif
            </p>

            @if (! auth()->user()->hasTwoFactorSecret())
                <x-button wire:click="enableTwoFactor" wire:loading.attr="disabled">
                    Zwei-Faktor-Authentifizierung aktivieren
                </x-button>
            @else
                @if ($showingQrCode)
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Scannen Sie den Code mit Ihrer Authenticator-App und bestätigen Sie
                            anschließend mit dem angezeigten Einmalcode.
                        </p>

                        <div class="inline-block rounded-md bg-white p-4">
                            {!! auth()->user()->twoFactorQrCodeSvg() !!}
                        </div>

                        <form wire:submit="confirmTwoFactor" class="flex max-w-xs items-end gap-2">
                            <x-input label="Code" wire:model="code" inputmode="numeric" class="flex-1" />
                            <x-button type="submit" wire:loading.attr="disabled">Bestätigen</x-button>
                        </form>
                    </div>
                @endif

                @if (auth()->user()->hasTwoFactorEnabled())
                    <div class="flex flex-wrap gap-2">
                        <x-button color="secondary" outline wire:click="showRecoveryCodes">
                            Wiederherstellungscodes anzeigen
                        </x-button>

                        <x-button color="secondary" outline wire:click="regenerateRecoveryCodes">
                            Neue Wiederherstellungscodes erzeugen
                        </x-button>

                        <x-button color="red"
                                  x-on:click="$dialog.confirm({
                                      title: 'Zwei-Faktor-Authentifizierung deaktivieren?',
                                      description: 'Ihr Konto ist danach nur noch durch das Passwort geschützt.',
                                      accept: { text: 'Deaktivieren', method: 'disableTwoFactor' },
                                      reject: { text: 'Abbrechen' },
                                  })">
                            Deaktivieren
                        </x-button>
                    </div>
                @endif

                @if ($recoveryCodes !== [])
                    <div class="rounded-md bg-gray-50 p-4 dark:bg-dark-700">
                        <p class="mb-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                            Wiederherstellungscodes
                        </p>
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            Bewahren Sie diese Codes sicher auf. Jeder Code kann einmal verwendet werden,
                            falls Sie keinen Zugriff auf Ihre Authenticator-App haben.
                        </p>
                        <div class="grid grid-cols-1 gap-1 font-mono text-sm text-gray-700 sm:grid-cols-2 dark:text-gray-200">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <div>{{ $recoveryCode }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Aktive Sitzungen</h2>
        </x-slot:header>

        <div class="space-y-4">
            <div class="divide-y divide-gray-200 dark:divide-dark-600">
                @foreach ($sessions as $session)
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="session-{{ $session->id }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ $session->deviceLabel() }}
                                @if ($session->isCurrent())
                                    <x-badge color="green" text="Diese Sitzung" sm class="ml-1" />
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $session->ip_address }} · zuletzt aktiv {{ $session->lastActivityAt()->diffForHumans() }}
                            </p>
                        </div>

                        @unless ($session->isCurrent())
                            <x-button color="secondary" outline sm wire:click="terminateSession('{{ $session->id }}')">
                                Beenden
                            </x-button>
                        @endunless
                    </div>
                @endforeach
            </div>

            @if ($sessions->count() > 1)
                <x-button color="secondary" outline
                          x-on:click="$dialog.confirm({
                              title: 'Alle anderen Sitzungen beenden?',
                              description: 'Sie bleiben nur auf diesem Gerät angemeldet.',
                              accept: { text: 'Beenden', method: 'terminateOtherSessions' },
                              reject: { text: 'Abbrechen' },
                          })">
                    Alle anderen Sitzungen beenden
                </x-button>
            @endif
        </div>
    </x-card>

    @script
    <script>
        $wire.on('zwei-faktor-aktiviert', () => $tallstackui.toast().success('Zwei-Faktor-Authentifizierung aktiviert').send());
        $wire.on('zwei-faktor-deaktiviert', () => $tallstackui.toast().success('Zwei-Faktor-Authentifizierung deaktiviert').send());
        $wire.on('wiederherstellungscodes-erneuert', () => $tallstackui.toast().success('Neue Wiederherstellungscodes erzeugt').send());
        $wire.on('sitzung-beendet', () => $tallstackui.toast().success('Sitzung beendet').send());
        $wire.on('sitzungen-beendet', () => $tallstackui.toast().success('Alle anderen Sitzungen wurden beendet').send());
    </script>
    @endscript
</div>
