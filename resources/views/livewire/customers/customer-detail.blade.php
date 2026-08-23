<div>
    <div class="mb-6">
        <a href="{{ route('customers.index') }}"
           wire:navigate
           class="text-sm text-primary-600 hover:underline dark:text-primary-400">
            &larr; Zurück zur Kundenliste
        </a>

        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $customer->displayName() }}
                    </h1>
                    <x-badge :color="$customer->status->color()" :text="$customer->status->label()" sm />
                    <x-badge color="gray" :text="$customer->type->label()" sm />
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $customer->customer_number }} · {{ $customer->short_label }} · {{ $customer->internal_code }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @unless ($customer->isArchived())
                    <x-button color="secondary"
                              outline
                              icon="pencil"
                              :href="route('customers.edit', $customer)"
                              wire:navigate>
                        Bearbeiten
                    </x-button>

                    <x-button color="red"
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
                    <x-button color="secondary"
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
            </div>
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
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Stammdaten</h2>
                    </x-slot:header>

                    <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
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

                @if ($customer->isPrivate())
                    <x-card>
                        <x-slot:header>
                            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Kontaktdaten</h2>
                        </x-slot:header>

                        <div class="space-y-4 text-sm">
                            <div>
                                <p class="mb-1 font-medium text-gray-700 dark:text-gray-200">E-Mail-Adressen</p>

                                @forelse ($customer->emailAddresses as $email)
                                    <p class="text-gray-600 dark:text-gray-300">
                                        {{ $email->email }}
                                        <span class="text-gray-400">({{ $email->type->label() }})</span>
                                        @if ($email->is_primary)
                                            <x-badge color="green" text="Primär" sm class="ml-1" />
                                        @endif
                                    </p>
                                @empty
                                    <p class="text-gray-400">Keine hinterlegt.</p>
                                @endforelse
                            </div>

                            <div>
                                <p class="mb-1 font-medium text-gray-700 dark:text-gray-200">Telefonnummern</p>

                                @forelse ($customer->phoneNumbers as $phone)
                                    <p class="text-gray-600 dark:text-gray-300">
                                        {{ $phone->number }}
                                        <span class="text-gray-400">({{ $phone->type->label() }})</span>
                                        @if ($phone->is_primary)
                                            <x-badge color="green" text="Primär" sm class="ml-1" />
                                        @endif
                                    </p>
                                @empty
                                    <p class="text-gray-400">Keine hinterlegt.</p>
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
                    <x-not-yet-available area="Ansprechpartner" />
                </x-card>
            </x-tab.items>
        @endif

        <x-tab.items tab="leistungen" title="Leistungen">
            <x-card>
                <x-not-yet-available area="Leistungen" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="notizen" title="Notizen">
            <x-card>
                <x-not-yet-available area="Notizen" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="dokumente" title="Dokumente">
            <x-card>
                <x-not-yet-available area="Dokumente" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="historie" title="Historie">
            <x-card>
                <x-not-yet-available area="Historie" />
            </x-card>
        </x-tab.items>
    </x-tab>

    @script
    <script>
        $wire.on('kunde-archiviert', () => $tallstackui.toast().success('Kunde archiviert').send());
        $wire.on('kunde-reaktiviert', () => $tallstackui.toast().success('Archivierung aufgehoben').send());
    </script>
    @endscript
</div>
