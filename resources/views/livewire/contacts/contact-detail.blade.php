<div>
    <x-page title="{{ $contact->fullName() }}"
            subtitle="{{ 'Bevorzugte Kontaktart: '.($contact->preferred_contact_method?->label() ?? 'nicht festgelegt') }}"
            back-label="Ansprechpartner ／ zurück zur Liste"
            back-url="{{ route('contacts.index') }}">
        <x-slot:actions>
            <x-button sm color="secondary"
                  outline
                  icon="pencil"
                  :href="route('contacts.edit', $contact)"
                  wire:navigate>
            Bearbeiten
                </x-button>

                @if ($contact->isArchived())
            <x-button sm color="secondary" outline icon="arrow-uturn-left" wire:click="restore">
                Archivierung aufheben
            </x-button>
                @else
            <x-button sm color="red"
                      outline
                      icon="archive-box"
                      x-on:click="$tsui.interaction('dialog')
                          .question('Ansprechpartner archivieren?', 'Die Kundenzuordnungen bleiben erhalten.')
                          .wireable($wire.id)
                          .confirm('Archivieren', 'archive')
                          .cancel('Abbrechen')
                          .send()">
                Archivieren
            </x-button>
                @endif
        </x-slot:actions>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            @if ($contact->isArchived())
                <x-badge color="gray" text="Archiviert" sm />
            @else
                <x-badge color="green" text="Aktiv" sm />
            @endif
        </div>

        @if (session('erfolg'))
            <x-alert color="green" class="mb-4">{{ session('erfolg') }}</x-alert>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Stammdaten</h2>
                </x-slot:header>

                <dl class="divide-y divide-line text-sm">
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
                    <h2 class="text-sm font-semibold text-ink">Kontaktdaten</h2>
                </x-slot:header>

                <div class="space-y-4 text-sm">
                    <div>
                        <p class="mb-1 font-medium text-ink-base">E-Mail-Adressen</p>

                        @forelse ($contact->emailAddresses as $email)
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

                        @forelse ($contact->phoneNumbers as $phone)
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
        </div>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Kundenzuordnungen</h2>
            </x-slot:header>

            <div class="divide-y divide-line">
                @foreach ($contact->assignments as $assignment)
                    <div class="py-3" wire:key="assignment-{{ $assignment->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('customers.show', $assignment->customer) }}"
                               wire:navigate
                               class="font-medium text-accent hover:underline">
                                {{ $assignment->customer->displayName() }}
                            </a>

                            <span class="text-xs text-ink-faint">{{ $assignment->customer->customer_number }}</span>

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

                        <div class="mt-1 flex flex-wrap items-center gap-1 text-sm text-ink-muted">
                            @forelse ($assignment->roles as $role)
                                <x-badge color="gray" :text="$role->name" sm />
                            @empty
                                <span class="text-ink-faint">Keine Rolle zugewiesen</span>
                            @endforelse

                            <span class="ml-2">Priorität {{ $assignment->priority }}</span>
                        </div>

                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $assignment->effectiveEmail()?->email ?? '—' }}
                            ·
                            {{ $assignment->effectivePhone()?->number ?? '—' }}
                            @if ($assignment->effectiveContactMethod())
                                · bevorzugt {{ $assignment->effectiveContactMethod()->label() }}
                            @endif
                        </p>

                        @if ($assignment->note)
                            <p class="mt-1 text-sm text-ink-muted">{{ $assignment->note }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Notizen</h2>
            </x-slot:header>

            <livewire:shared.notes-panel :notable="$contact" :key="'notizen-kontakt-'.$contact->id" />
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Dokumente</h2>
            </x-slot:header>

            <livewire:shared.documents-panel :documentable="$contact" :key="'dokumente-kontakt-'.$contact->id" />
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Historie</h2>
            </x-slot:header>

            <livewire:shared.audit-panel :auditable="$contact" :key="'historie-kontakt-'.$contact->id" />
        </x-card>

        @script
        <script>
            $wire.on('ansprechpartner-archiviert', () => $tsui.interaction('toast').success('Ansprechpartner archiviert').send());
            $wire.on('ansprechpartner-reaktiviert', () => $tsui.interaction('toast').success('Archivierung aufgehoben').send());
        </script>
        @endscript
    </x-page>
</div>
