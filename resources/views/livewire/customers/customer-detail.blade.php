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

        {{-- Kopfband: Initialen, Name, Status und die Kennzahlen des Kunden. --}}
        <div class="mb-4 flex flex-col gap-4 rounded-[10px] border border-line bg-panel px-4 py-4 lg:flex-row lg:items-center">
            <x-avatar-initials :initials="\Illuminate\Support\Str::of($customer->displayName())->substr(0, 2)->upper()" size="lg" />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="truncate text-[15px] font-semibold text-ink">{{ $customer->displayName() }}</span>
                    <x-status-pill :kind="$customer->isArchived() ? 'mute' : 'ok'" :label="$customer->status->label()" />
                    <x-status-pill kind="info" :label="$customer->type->label()" :dot="false" />
                </div>

                <p class="mt-1 text-[11.5px] text-ink-muted">
                    {{ $customer->customer_number }} · {{ $customer->short_label }}
                    @if ($customer->responsibleUser)
                        · betreut von {{ $customer->responsibleUser->name }}
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:w-auto">
                @foreach ([
                    ['Leistungen', number_format($activeServices, 0, ',', '.')],
                    ['Umsatz / Mon', $customer->monthlyRevenue()->format()],
                    ['Kosten / Mon', $customer->monthlyCosts()->format()],
                    ['Marge / Mon', $customer->monthlyMargin()->format()],
                ] as [$label, $value])
                    <div class="flex flex-col gap-1 rounded-lg border border-line bg-raised px-3 py-2"
                         wire:key="kennzahl-{{ $loop->index }}">
                        <span class="text-[9.5px] font-semibold uppercase tracking-[0.09em] text-ink-faint">{{ $label }}</span>
                        <span class="tabular text-[13px] font-semibold text-ink">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if (session('erfolg'))
            <x-alert color="green" class="mb-4">{{ session('erfolg') }}</x-alert>
        @endif

        @if ($customer->isArchived())
            <x-alert color="amber" class="mb-4" title="Archivierter Kunde">
                Dieser Kunde ist archiviert und erscheint nicht in der globalen Suche.
            </x-alert>
        @endif

        <x-tab wire:model="tab">
        <x-tab.items tab="uebersicht" title="Übersicht">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-card>
                    <x-slot:header>
                        <h2 class="text-sm font-semibold text-ink">Stammdaten</h2>
                    </x-slot:header>

                    <dl class="divide-y divide-line text-sm">
                        <x-detail-row label="Kundennummer" :value="$customer->customer_number" />
                        <x-detail-row label="Typ" :value="$customer->type->label()" />

                        @if ($customer->isCompany())
                            <x-detail-row label="Firmenname" :value="$customer->company_name" />
                        @else
                            <x-detail-row label="Anrede" :value="$customer->salutation?->label()" />
                            <x-detail-row label="Akademischer Titel" :value="$customer->academic_title" />
                            <x-detail-row label="Vorname" :value="$customer->first_name" />
                            <x-detail-row label="Nachname" :value="$customer->last_name" />
                            <x-detail-row label="Geburtsdatum" :value="$customer->birth_date?->format('d.m.Y')" />
                            <x-detail-row label="Geschlecht" :value="$customer->gender?->label()" />
                        @endif

                        <x-detail-row label="Kurzbezeichnung" :value="$customer->short_label" />
                        <x-detail-row label="Internes Kürzel" :value="$customer->internal_code" />
                        <x-detail-row label="Interner Verantwortlicher" :value="$customer->responsibleUser?->name" />
                        <x-detail-row label="Angelegt" :value="$customer->created_at->format('d.m.Y H:i')" />
                        <x-detail-row label="Zuletzt geändert" :value="$customer->updated_at->format('d.m.Y H:i')" />

                        @if ($customer->archived_at)
                            <x-detail-row label="Archiviert" :value="$customer->archived_at->format('d.m.Y H:i')" />
                        @endif
                    </dl>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <h2 class="text-sm font-semibold text-ink">Benutzerdefinierte Felder</h2>
                    </x-slot:header>

                    <livewire:custom-fields.custom-fields-panel :record="$customer"
                                                                :read-only="$customer->isArchived()"
                                                                :key="'felder-'.$customer->id" />
                </x-card>

                @if ($customer->isPrivate())
                    <x-card>
                        <x-slot:header>
                            <h2 class="text-sm font-semibold text-ink">Kontaktdaten</h2>
                        </x-slot:header>

                        <div class="space-y-4 text-sm">
                            <div>
                                <p class="mb-1 font-medium text-ink-base">E-Mail-Adressen</p>

                                @forelse ($customer->emailAddresses as $email)
                                    <p class="text-ink-base">
                                        {{ $email->email }}
                                        <span class="text-ink-faint">({{ $email->type->label() }})</span>
                                        @if ($email->is_primary)
                                            <x-badge color="green" text="Primär" sm class="ml-1" />
                                        @endif
                                    </p>
                                @empty
                                    <p class="text-ink-faint">Keine hinterlegt.</p>
                                @endforelse
                            </div>

                            <div>
                                <p class="mb-1 font-medium text-ink-base">Telefonnummern</p>

                                @forelse ($customer->phoneNumbers as $phone)
                                    <p class="text-ink-base">
                                        {{ $phone->number }}
                                        <span class="text-ink-faint">({{ $phone->type->label() }})</span>
                                        @if ($phone->is_primary)
                                            <x-badge color="green" text="Primär" sm class="ml-1" />
                                        @endif
                                    </p>
                                @empty
                                    <p class="text-ink-faint">Keine hinterlegt.</p>
                                @endforelse
                            </div>
                        </div>
                    </x-card>
                @endif
            </div>
        </x-tab.items>

        @if ($customer->isCompany())
            <x-tab.items tab="ansprechpartner" title="Ansprechpartner">
                <x-card>
                    <livewire:contacts.customer-contacts :customer="$customer" :key="'kontakte-'.$customer->id" />
                </x-card>
            </x-tab.items>
        @endif

        <x-tab.items tab="leistungen" title="Leistungen">
            <x-card>
                <livewire:services.customer-services :customer="$customer" :key="'leistungen-'.$customer->id" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="notizen" title="Notizen">
            <x-card>
                <livewire:shared.notes-panel :notable="$customer" :key="'notizen-'.$customer->id" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="dokumente" title="Dokumente">
            <x-card>
                <livewire:shared.documents-panel :documentable="$customer" :key="'dokumente-'.$customer->id" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="historie" title="Historie">
            <x-card>
                <livewire:shared.audit-panel :auditable="$customer" :key="'historie-'.$customer->id" />
            </x-card>
        </x-tab.items>
    </x-tab>

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
