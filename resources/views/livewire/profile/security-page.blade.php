<div>
    <x-page title="Sicherheit" subtitle="Zwei-Faktor-Authentifizierung und aktive Sitzungen." class="flex flex-col gap-4">

        @if (session('warning'))
            <x-alert color="amber">{{ session('warning') }}</x-alert>
        @endif

        <x-card>
            <x-slot:header>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-ink">Zwei-Faktor-Authentifizierung</h2>

                    @if (auth()->user()->hasTwoFactorEnabled())
                        <x-badge color="green" text="Aktiv" sm />
                    @else
                        <x-badge color="gray" text="Nicht aktiv" sm />
                    @endif
                </div>
            </x-slot:header>

            <div class="space-y-4">
                <p class="text-sm text-ink-base">
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
                            <p class="text-sm text-ink-base">
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
                                      x-on:click="$tsui.interaction('dialog')
                                          .question('Zwei-Faktor-Authentifizierung deaktivieren?', 'Ihr Konto ist danach nur noch durch das Passwort geschützt.')
                                          .wireable($wire.id)
                                          .confirm('Deaktivieren', 'disableTwoFactor')
                                          .cancel('Abbrechen')
                                          .send()">
                                Deaktivieren
                            </x-button>
                        </div>
                    @endif

                    @if ($recoveryCodes !== [])
                        <div class="rounded-md bg-raised p-4">
                            <p class="mb-2 text-sm font-medium text-ink">
                                Wiederherstellungscodes
                            </p>
                            <p class="mb-3 text-xs text-ink-muted">
                                Bewahren Sie diese Codes sicher auf. Jeder Code kann einmal verwendet werden,
                                falls Sie keinen Zugriff auf Ihre Authenticator-App haben.
                            </p>
                            <div class="grid grid-cols-1 gap-1 font-mono text-sm text-ink-base sm:grid-cols-2">
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
                <h2 class="text-sm font-semibold text-ink">Aktive Sitzungen</h2>
            </x-slot:header>

            <div class="space-y-4">
                <div class="divide-y divide-line">
                    @foreach ($sessions as $session)
                        <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="session-{{ $session->id }}">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">
                                    {{ $session->deviceLabel() }}
                                    @if ($session->isCurrent())
                                        <x-badge color="green" text="Diese Sitzung" sm class="ml-1" />
                                    @endif
                                </p>
                                <p class="text-xs text-ink-muted">
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
                              x-on:click="$tsui.interaction('dialog')
                                  .question('Alle anderen Sitzungen beenden?', 'Sie bleiben nur auf diesem Gerät angemeldet.')
                                  .wireable($wire.id)
                                  .confirm('Beenden', 'terminateOtherSessions')
                                  .cancel('Abbrechen')
                                  .send()">
                        Alle anderen Sitzungen beenden
                    </x-button>
                @endif
            </div>
        </x-card>

        @script
        <script>
            $wire.on('zwei-faktor-aktiviert', () => $tsui.interaction('toast').success('Zwei-Faktor-Authentifizierung aktiviert').send());
            $wire.on('zwei-faktor-deaktiviert', () => $tsui.interaction('toast').success('Zwei-Faktor-Authentifizierung deaktiviert').send());
            $wire.on('wiederherstellungscodes-erneuert', () => $tsui.interaction('toast').success('Neue Wiederherstellungscodes erzeugt').send());
            $wire.on('sitzung-beendet', () => $tsui.interaction('toast').success('Sitzung beendet').send());
            $wire.on('sitzungen-beendet', () => $tsui.interaction('toast').success('Alle anderen Sitzungen wurden beendet').send());
        </script>
        @endscript
    </x-page>
</div>
