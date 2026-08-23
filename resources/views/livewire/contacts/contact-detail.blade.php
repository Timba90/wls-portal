<div>
    <div class="mb-6">
        <a href="{{ route('contacts.index') }}"
           wire:navigate
           class="text-sm text-primary-600 hover:underline dark:text-primary-400">
            &larr; Zurück zur Ansprechpartnerliste
        </a>

        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $contact->fullName() }}
                    </h1>

                    @if ($contact->isArchived())
                        <x-badge color="gray" text="Archiviert" sm />
                    @else
                        <x-badge color="green" text="Aktiv" sm />
                    @endif
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Bevorzugte Kontaktart: {{ $contact->preferred_contact_method?->label() ?? 'nicht festgelegt' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-button color="secondary"
                          outline
                          icon="pencil"
                          :href="route('contacts.edit', $contact)"
                          wire:navigate>
                    Bearbeiten
                </x-button>

                @if ($contact->isArchived())
                    <x-button color="secondary" outline icon="arrow-uturn-left" wire:click="restore">
                        Archivierung aufheben
                    </x-button>
                @else
                    <x-button color="red"
                              outline
                              icon="archive-box"
                              x-on:click="$dialog.confirm({
                                  title: 'Ansprechpartner archivieren?',
                                  description: 'Die Kundenzuordnungen bleiben erhalten.',
                                  accept: { text: 'Archivieren', method: 'archive' },
                                  reject: { text: 'Abbrechen' },
                              })">
                        Archivieren
                    </x-button>
                @endif
            </div>
        </div>
    </div>

    @if (session('erfolg'))
        <x-alert color="green" class="mb-4">{{ session('erfolg') }}</x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Stammdaten</h2>
            </x-slot:header>

            <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
                <x-detail-row label="Anrede" :value="$contact->salutation?->label()" />
                <x-detail-row label="Akademischer Titel" :value="$contact->academic_title" />
                <x-detail-row label="Vorname" :value="$contact->first_name" />
                <x-detail-row label="Nachname" :value="$contact->last_name" />
                <x-detail-row label="Geschlecht" :value="$contact->gender?->label()" />
                <x-detail-row label="Geburtsdatum" :value="$contact->birth_date?->format('d.m.Y')" />
                <x-detail-row label="Bevorzugte Kontaktart" :value="$contact->preferred_contact_method?->label()" />
            </dl>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Kontaktdaten</h2>
            </x-slot:header>

            <div class="space-y-4 text-sm">
                <div>
                    <p class="mb-1 font-medium text-gray-700 dark:text-gray-200">E-Mail-Adressen</p>

                    @forelse ($contact->emailAddresses as $email)
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

                    @forelse ($contact->phoneNumbers as $phone)
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
    </div>

    <x-card class="mt-4">
        <x-slot:header>
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Kundenzuordnungen</h2>
        </x-slot:header>

        <div class="divide-y divide-gray-200 dark:divide-dark-600">
            @foreach ($contact->assignments as $assignment)
                <div class="py-3" wire:key="assignment-{{ $assignment->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('customers.show', $assignment->customer) }}"
                           wire:navigate
                           class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ $assignment->customer->displayName() }}
                        </a>

                        <span class="text-xs text-gray-400">{{ $assignment->customer->customer_number }}</span>

                        @if ($assignment->is_primary_contact)
                            <x-badge color="blue" text="Hauptansprechpartner" sm />
                        @endif

                        @if ($assignment->is_billing_contact)
                            <x-badge color="purple" text="Rechnungskontakt" sm />
                        @endif

                        @unless ($assignment->is_active)
                            <x-badge color="gray" text="Inaktiv" sm />
                        @endunless
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                        @forelse ($assignment->roles as $role)
                            <x-badge color="gray" :text="$role->name" sm />
                        @empty
                            <span class="text-gray-400">Keine Rolle zugewiesen</span>
                        @endforelse

                        <span class="ml-2">Priorität {{ $assignment->priority }}</span>
                    </div>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $assignment->effectiveEmail()?->email ?? '—' }}
                        ·
                        {{ $assignment->effectivePhone()?->number ?? '—' }}
                        @if ($assignment->effectiveContactMethod())
                            · bevorzugt {{ $assignment->effectiveContactMethod()->label() }}
                        @endif
                    </p>

                    @if ($assignment->note)
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $assignment->note }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>

    @script
    <script>
        $wire.on('ansprechpartner-archiviert', () => $tallstackui.toast().success('Ansprechpartner archiviert').send());
        $wire.on('ansprechpartner-reaktiviert', () => $tallstackui.toast().success('Archivierung aufgehoben').send());
    </script>
    @endscript
</div>
