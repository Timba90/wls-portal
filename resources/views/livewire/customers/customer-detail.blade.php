@php
    $reiter = collect([
        'leistungen' => 'Leistungen',
        'notizen' => 'Notizen',
        'dokumente' => 'Dokumente',
        'felder' => 'Eigene Felder',
        'historie' => 'Historie',
    ]);
@endphp

<div>
    <x-page :title="$customer->displayName()"
            :subtitle="$customer->customer_number.' · '.$customer->short_label.' · '.$customer->internal_code"
            back-label="Kunden ／ zurück zur Liste"
            :back-url="route('customers.index')">
        <x-slot:actions>
            @unless ($customer->isArchived())
                <x-button sm color="secondary" outline icon="pencil"
                          :href="route('customers.edit', $customer)" wire:navigate>
                    Bearbeiten
                </x-button>

                <x-button sm
                          color="red"
                          outline
                          icon="archive-box"
                          x-on:click="$dialog.confirm({
                              title: 'Kunde archivieren?',
                              description: 'Der Kunde bleibt vollständig erhalten, erscheint aber nicht mehr in der normalen Suche.',
                              accept: { text: 'Archivieren', method: 'archive' },
                              reject: { text: 'Abbrechen' },
                          })">
                    Archivieren
                </x-button>
            @else
                <x-button sm
                          color="secondary"
                          outline
                          icon="arrow-uturn-left"
                          x-on:click="$dialog.confirm({
                              title: 'Archivierung aufheben?',
                              description: 'Der Kunde wird wieder als aktiv geführt.',
                              accept: { text: 'Reaktivieren', method: 'restore' },
                              reject: { text: 'Abbrechen' },
                          })">
                    Archivierung aufheben
                </x-button>
            @endunless
        </x-slot:actions>

        @if (session('erfolg'))
            <x-alert color="green" class="mb-3.5">{{ session('erfolg') }}</x-alert>
        @endif

        @if ($customer->isArchived())
            <x-alert color="amber" class="mb-3.5" title="Archivierter Kunde">
                Dieser Kunde ist archiviert und erscheint nicht in der globalen Suche.
            </x-alert>
        @endif

        {{-- Kopfkarte: Initialen, Name mit Status, darunter die Kennzahlenreihe. --}}
        <div class="mb-3.5 flex flex-wrap items-center gap-4 rounded-[10px] border border-line bg-panel px-[17px] py-4">
            <x-avatar-initials :initials="$customer->initials()" size="lg" />

            <div class="flex min-w-[200px] flex-[1_1_220px] flex-col gap-[5px]">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-[16px] font-semibold tracking-[-0.015em] text-ink">
                        {{ $customer->displayName() }}
                    </span>
                    <x-status-pill :kind="$customer->isArchived() ? 'mute' : 'ok'" :label="$customer->status->label()" />
                </div>

                <span class="text-[11.5px] text-ink-muted">
                    {{ $customer->type->label() }} · {{ $customer->customer_number }}
                    @if ($customer->responsibleUser)
                        · betreut von {{ $customer->responsibleUser->name }}
                    @endif
                </span>
            </div>

            <div class="ml-auto flex flex-wrap gap-[22px]">
                @foreach ([
                    ['Leistungen', number_format($activeServices, 0, ',', '.'), false],
                    ['Umsatz / Mon', $customer->monthlyRevenue()->format(), false],
                    ['Kosten / Mon', $customer->monthlyCosts()->format(), false],
                    ['Marge / Mon', $customer->monthlyMargin()->format(), $customer->monthlyMargin()->isNegative()],
                ] as [$label, $wert, $negativ])
                    <div class="flex flex-col gap-1" wire:key="kennzahl-{{ $loop->index }}">
                        <span class="text-[9.5px] font-semibold uppercase tracking-[0.09em] text-ink-faint">
                            {{ $label }}
                        </span>
                        <span @class([
                            'tabular text-[15px] font-semibold',
                            'text-[color:var(--pill-bad-ink)]' => $negativ,
                            'text-ink' => ! $negativ,
                        ])>{{ $wert }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
            {{-- Linke Spalte: Reiter mit den umfangreichen Inhalten. --}}
            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex gap-1 rounded-[9px] border border-line bg-panel p-1">
                    @foreach ($reiter as $schluessel => $beschriftung)
                        <button type="button"
                                wire:click="$set('tab', '{{ $schluessel }}')"
                                @class([
                                    'flex-1 rounded-[6px] px-3 py-1.5 text-[12px] font-medium transition',
                                    'bg-accent text-accent-ink' => $tab === $schluessel,
                                    'text-ink-muted hover:bg-raised hover:text-ink-base' => $tab !== $schluessel,
                                ])>{{ $beschriftung }}</button>
                    @endforeach
                </div>

                @switch($tab)
                    @case('notizen')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.notes-panel :notable="$customer" :key="'notizen-'.$customer->id" />
                        </div>
                        @break

                    @case('dokumente')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.documents-panel :documentable="$customer" :key="'dokumente-'.$customer->id" />
                        </div>
                        @break

                    @case('felder')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:custom-fields.custom-fields-panel :record="$customer"
                                                                        :read-only="$customer->isArchived()"
                                                                        :key="'felder-'.$customer->id" />
                        </div>
                        @break

                    @case('historie')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.audit-panel :auditable="$customer" :key="'historie-'.$customer->id" />
                        </div>
                        @break

                    @default
                        <livewire:services.customer-services :customer="$customer" :key="'leistungen-'.$customer->id" />
                @endswitch
            </div>

            {{-- Rechte Spalte: Stammdaten, Ansprechpartner und Zusatzfelder. --}}
            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Stammdaten</span>

                    @foreach ($this->masterData() as $label => $wert)
                        <div class="flex items-baseline justify-between gap-3" wire:key="stamm-{{ $loop->index }}">
                            <span class="text-[11.5px] text-ink-muted">{{ $label }}</span>
                            <span class="truncate text-right text-[12px] text-ink-base">{{ blank($wert) ? '—' : $wert }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($customer->isCompany())
                    <div class="flex flex-col gap-3 rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">
                                Ansprechpartner
                            </span>
                            <a href="{{ route('contacts.create') }}"
                               wire:navigate
                               class="text-[11px] text-accent hover:underline">hinzufügen</a>
                        </div>

                        @forelse ($customer->contactAssignments as $zuordnung)
                            <a href="{{ route('contacts.show', $zuordnung->contact) }}"
                               wire:navigate
                               class="flex items-center gap-[11px]"
                               wire:key="kontakt-{{ $zuordnung->id }}">
                                <span class="flex h-[26px] w-[26px] flex-none items-center justify-center rounded-full bg-raised text-[10px] font-semibold text-ink-base">
                                    {{ Str::upper(Str::substr($zuordnung->contact->first_name, 0, 1).Str::substr($zuordnung->contact->last_name, 0, 1)) }}
                                </span>

                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate text-[12.5px] text-ink-base">
                                        {{ $zuordnung->contact->fullName() }}
                                    </span>
                                    <span class="truncate font-mono text-[10.5px] text-ink-faint">
                                        {{ $zuordnung->effectiveEmail()?->email }}
                                    </span>
                                </span>

                                <span class="whitespace-nowrap text-[10.5px] text-ink-faint">
                                    {{ $zuordnung->roles->pluck('name')->join(', ') ?: '—' }}
                                </span>
                            </a>
                        @empty
                            <p class="text-[11.5px] text-ink-faint">Noch kein Ansprechpartner zugeordnet.</p>
                        @endforelse
                    </div>
                @endif

                @if ($customer->isPrivate())
                    <div class="flex flex-col gap-3 rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                        <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Kontaktdaten</span>

                        @forelse ($customer->emailAddresses as $adresse)
                            <div class="flex items-baseline justify-between gap-3" wire:key="mail-{{ $adresse->id }}">
                                <span class="truncate font-mono text-[11.5px] text-ink-base">{{ $adresse->email }}</span>
                                <span class="whitespace-nowrap text-[10.5px] text-ink-faint">{{ $adresse->type->label() }}</span>
                            </div>
                        @empty
                            <p class="text-[11.5px] text-ink-faint">Keine E-Mail-Adresse hinterlegt.</p>
                        @endforelse

                        @foreach ($customer->phoneNumbers as $nummer)
                            <div class="flex items-baseline justify-between gap-3" wire:key="tel-{{ $nummer->id }}">
                                <span class="truncate font-mono text-[11.5px] text-ink-base">{{ $nummer->number }}</span>
                                <span class="whitespace-nowrap text-[10.5px] text-ink-faint">{{ $nummer->type->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        </div>

        @script
        <script>
            $wire.on('kunde-archiviert', () => $tallstackui.toast().success('Kunde archiviert').send());
            $wire.on('kunde-reaktiviert', () => $tallstackui.toast().success('Archivierung aufgehoben').send());
            $wire.on('archivierung-nicht-moeglich', (event) =>
                $tallstackui.toast().error('Archivierung nicht möglich', event.meldung).send());
        </script>
        @endscript
    </x-page>
</div>
