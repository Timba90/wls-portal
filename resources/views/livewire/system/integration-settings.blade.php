<div>
    <x-page title="Schnittstellen"
            subtitle="Zugangsdaten der Registrare. Sie liegen verschlüsselt in der Datenbank.">

        <div class="flex flex-col gap-4">
            {{--
                Die Felder sind einseitig: was hinterlegt ist, wird nie
                zurückgelesen. Angezeigt wird nur, ob etwas hinterlegt ist.
            --}}
            @foreach ($providers as $anbieter)
                @php
                    $felder = $this->fieldsFor($anbieter);
                    $hinterlegt = $this->storedFields($anbieter);
                    $letzte = $this->lastChange($anbieter);
                    $hinweis = $this->notice($anbieter);
                    // Ob ein Anschluss bereit ist, weiß er selbst am besten —
                    // bei dem einen sind es Zugangsdaten, beim anderen eine
                    // aufrufbare Brücke.
                    $bereit = $this->isReady($anbieter);
                    $abgleich = $this->lastSync($anbieter);
                @endphp

                <x-card wire:key="anbieter-{{ $anbieter->value }}">
                    <x-slot:header>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-ink">{{ $anbieter->label() }}</h2>

                            <x-status-pill :kind="$bereit ? 'ok' : 'mute'"
                                           :label="$bereit ? 'Eingerichtet' : 'Nicht eingerichtet'" />
                        </div>
                    </x-slot:header>

                    @if ($hinweis)
                        <p class="rounded-[8px] border border-line bg-raised px-3 py-2.5 text-[12.5px] leading-relaxed text-ink-muted">
                            {{ $hinweis }}
                        </p>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($felder as $name => $feld)
                            <div wire:key="feld-{{ $anbieter->value }}-{{ $name }}">
                                @php($geheim = $feld['secret'] ?? true)

                                <x-input :type="$geheim ? 'password' : 'text'"
                                         :autocomplete="$geheim ? 'new-password' : 'off'"
                                         wire:model="input.{{ $anbieter->value }}.{{ $name }}"
                                         :label="$feld['label']"
                                         :placeholder="($hinterlegt[$name] ?? false) ? 'Hinterlegt — zum Ersetzen neu eingeben' : 'Nicht hinterlegt'"
                                         :hint="$feld['hint'] ?? null" />
                            </div>
                        @endforeach
                    </div>

                    @if ($abgleich)
                        {{-- Der letzte Lauf, im Guten wie im Schlechten. --}}
                        <div @class([
                            'mt-4 rounded-[8px] border px-3 py-2.5 text-[12.5px] leading-relaxed',
                            'border-[color:var(--pill-bad-line)] bg-[color:var(--pill-bad-bg)] text-[color:var(--pill-bad-ink)]' => $abgleich->isFailed(),
                            'border-line bg-raised text-ink-muted' => ! $abgleich->isFailed(),
                        ])>
                            <span class="font-medium">
                                {{ $abgleich->isFailed() ? 'Letzter Abgleich fehlgeschlagen' : 'Zuletzt abgeglichen' }}
                            </span>
                            am {{ $abgleich->started_at->format('d.m.Y H:i') }}
                            ({{ $abgleich->trigger === 'scheduled' ? 'planmäßig' : 'von Hand' }})

                            @if ($abgleich->isFailed())
                                <span class="mt-1 block">{{ $abgleich->error }}</span>
                            @else
                                —
                                {{ $abgleich->domains_new + $abgleich->certificates_new }} neu,
                                {{ $abgleich->domains_updated + $abgleich->certificates_updated }} geändert@if ($abgleich->skipped > 0),
                                    {{ $abgleich->skipped }} übergangen@endif.
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                        <span class="text-[11px] text-ink-faint">
                            @if ($letzte)
                                Zuletzt geändert am {{ $letzte->updated_at->format('d.m.Y H:i') }}
                                @if ($letzte->updatedBy)
                                    von {{ $letzte->updatedBy->name }}
                                @endif
                            @elseif ($felder === [])
                                Zugangsdaten liegen außerhalb des Portals.
                            @else
                                Noch nichts hinterlegt.
                            @endif
                        </span>

                        <div class="flex gap-2">
                            @if ($letzte)
                                <x-button sm
                                          color="red"
                                          outline
                                          x-on:click="$tsui.interaction('dialog')
                                              .question('Zugangsdaten entfernen?', 'Der Anschluss ist danach nicht mehr eingerichtet und der Import bricht ab.')
                                              .wireable($wire.id)
                                              .confirm('Entfernen', 'forget', '{{ $anbieter->value }}')
                                              .cancel('Abbrechen')
                                              .send()">
                                    Entfernen
                                </x-button>
                            @endif

                            @if ($bereit)
                                <x-button sm
                                          color="secondary"
                                          outline
                                          icon="signal"
                                          wire:click="test('{{ $anbieter->value }}')"
                                          wire:loading.attr="disabled"
                                          wire:target="test('{{ $anbieter->value }}')">
                                    Verbindung prüfen
                                </x-button>
                            @endif

                            @if ($felder !== [])
                                <x-button sm wire:click="save('{{ $anbieter->value }}')">Speichern</x-button>
                            @endif
                        </div>
                    </div>
                </x-card>
            @endforeach

            <x-card>
                <x-slot:header>Flux REST-API</x-slot:header>
                <p class="mb-4 text-sm text-ink-muted">Mit deinem Flux-Benutzer anmelden oder ein vorhandenes API-Token hinterlegen. E-Mail und Passwort werden nur für die Anmeldung verwendet; gespeichert wird das Token.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-input label="REST-API-Adresse" wire:model="fluxInput.base_url" />
                    <x-input label="Mandanten-ID" wire:model="fluxInput.tenant_id" />
                    <x-input label="API-Token" type="password" autocomplete="new-password" wire:model="fluxInput.token" placeholder="Zum Hinterlegen oder Ersetzen eingeben" hint="Leer lassen, um das gespeicherte Token beizubehalten." />
                </div>
                <form wire:submit="loginFlux" class="mt-4 grid gap-4 border-t border-line pt-4 md:grid-cols-2">
                    <x-input label="Flux-E-Mail / Benutzername" autocomplete="off" wire:model="fluxLogin.username" />
                    <x-input label="Flux-Passwort" type="password" autocomplete="new-password" wire:model="fluxLogin.password" />
                    <x-button type="submit" wire:loading.attr="disabled">Mit Flux anmelden und Token speichern</x-button>
                </form>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button wire:click="saveFlux" wire:loading.attr="disabled">Speichern</x-button>
                    <x-button wire:click="testFlux" wire:loading.attr="disabled">Verbindung prüfen</x-button>
                    <x-button color="red" outline wire:click="forgetFlux" wire:confirm="Flux-Zugangsdaten entfernen?">Entfernen</x-button>
                    <a class="self-center underline" href="{{ route('ledger-accounts.index') }}">Sachkonten öffnen</a>
                </div>
            </x-card>

            <x-panel title="Wie es weitergeht" subtitle="Nach dem Hinterlegen der Zugangsdaten">
                <p class="text-[12.5px] leading-relaxed text-ink-muted">
                    Der erste Schritt ist ein Trockenlauf. Er zeigt, was der Import anlegen und ändern
                    würde, ohne etwas zu schreiben:
                </p>

                <p class="mt-2 font-mono text-[12px] text-ink-base">php artisan registrar:import --trocken</p>

                <p class="mt-3 text-[12.5px] leading-relaxed text-ink-muted">
                    Davor beantwortet „Verbindung prüfen" die einfachere Frage: antwortet der Anbieter
                    überhaupt? Bei autoDNS geht der Aufruf an <span class="font-mono">/hello</span>, bei
                    ResellerInterface an <span class="font-mono">domain/check</span> mit einem freien
                    Testnamen. Beide lesen nichts und ändern nichts. Dasselbe von der Kommandozeile:
                </p>

                <p class="mt-2 font-mono text-[12px] text-ink-base">php artisan registrar:test</p>

                <p class="mt-3 text-[12.5px] leading-relaxed text-ink-muted">
                    Schlägt ein Aufruf fehl, wird er nicht wiederholt. Bei ResellerInterface verlängert
                    jeder weitere Versuch eine Sperre — der Anschluss bricht deshalb ab und meldet den
                    Wortlaut, statt es noch einmal zu probieren.
                </p>
            </x-panel>
        </div>
    </x-page>

    @script
    <script>
        $wire.on('zugang-gespeichert', () => $tsui.interaction('toast').success('Zugangsdaten gespeichert').send());
        $wire.on('zugang-entfernt', () => $tsui.interaction('toast').success('Zugangsdaten entfernt').send());
        $wire.on('zugang-unveraendert', () => $tsui.interaction('toast').info('Nichts eingegeben — nichts geändert').send());

        // Die Antwort des Anbieters wird im Wortlaut gezeigt: bei einer
        // Ablehnung ist genau sie der Hinweis, was fehlt.
        $wire.on('zugang-geprueft', (e) => $tsui.interaction('toast').success('Verbindung steht', e.meldung).send());
        $wire.on('zugang-abgelehnt', (e) => $tsui.interaction('toast').error('Verbindung abgelehnt', e.meldung).send());
    </script>
    @endscript
</div>
