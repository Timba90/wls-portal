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
                    $vollstaendig = collect($felder)
                        ->reject(fn (array $feld): bool => $feld['optional'] ?? false)
                        ->every(fn (array $feld, string $name): bool => $hinterlegt[$name] ?? false);
                @endphp

                <x-card wire:key="anbieter-{{ $anbieter->value }}">
                    <x-slot:header>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-ink">{{ $anbieter->label() }}</h2>

                            <x-status-pill :kind="$vollstaendig ? 'ok' : 'mute'"
                                           :label="$vollstaendig ? 'Eingerichtet' : 'Nicht eingerichtet'" />
                        </div>
                    </x-slot:header>

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

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                        <span class="text-[11px] text-ink-faint">
                            @if ($letzte)
                                Zuletzt geändert am {{ $letzte->updated_at->format('d.m.Y H:i') }}
                                @if ($letzte->updatedBy)
                                    von {{ $letzte->updatedBy->name }}
                                @endif
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

                            @if ($vollstaendig)
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

                            <x-button sm wire:click="save('{{ $anbieter->value }}')">Speichern</x-button>
                        </div>
                    </div>
                </x-card>
            @endforeach

            <x-panel title="Wie es weitergeht" subtitle="Nach dem Hinterlegen der Zugangsdaten">
                <p class="text-[12.5px] leading-relaxed text-ink-muted">
                    Der erste Schritt ist ein Trockenlauf. Er zeigt, was der Import anlegen und ändern
                    würde, ohne etwas zu schreiben:
                </p>

                <p class="mt-2 font-mono text-[12px] text-ink-base">php artisan registrar:import --trocken</p>

                <p class="mt-3 text-[12.5px] leading-relaxed text-ink-muted">
                    Davor beantwortet „Verbindung prüfen" die einfachere Frage: stimmen Zugangsdaten
                    und Kontext überhaupt? Der Aufruf geht an <span class="font-mono">/hello</span>,
                    liest nichts und ändert nichts. Dasselbe von der Kommandozeile:
                </p>

                <p class="mt-2 font-mono text-[12px] text-ink-base">php artisan registrar:test</p>
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
