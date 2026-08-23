<div>
    <x-page title="{{ $this->isEditing() ? 'Ansprechpartner bearbeiten' : 'Ansprechpartner anlegen' }}"
            subtitle="Mindestens eine Kundenzuordnung ist erforderlich."
            back-label="Ansprechpartner ／ zurück"
            back-url="{{ $this->isEditing() ? route('contacts.show', $contact) : route('contacts.index') }}">

        <form wire:submit="save" class="space-y-6">
            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Stammdaten</h2>
                </x-slot:header>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-select.styled wire:model="salutation"
                                     label="Anrede"
                                     placeholder="Bitte wählen"
                                     :options="$salutationOptions"
                                     select="label:label|value:value" />

                    <x-input wire:model="academic_title" label="Akademischer Titel" placeholder="z. B. Dr." />

                    <x-input wire:model="first_name" label="Vorname" required />
                    <x-input wire:model="last_name" label="Nachname" required />

                    <x-select.styled wire:model="gender"
                                     label="Geschlecht"
                                     placeholder="Bitte wählen"
                                     :options="$genderOptions"
                                     select="label:label|value:value" />

                    <x-date wire:model="birth_date" label="Geburtsdatum" format="DD.MM.YYYY" />

                    <x-select.styled wire:model="preferred_contact_method"
                                     label="Bevorzugte Kontaktart"
                                     placeholder="Bitte wählen"
                                     :options="$contactMethodOptions"
                                     select="label:label|value:value" />
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">E-Mail-Adressen</h2>
                </x-slot:header>

                <div class="space-y-3">
                    @foreach ($emails as $index => $email)
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end" wire:key="email-{{ $index }}">
                            <div class="flex-1">
                                <x-input wire:model="emails.{{ $index }}.email" type="email" label="E-Mail-Adresse" />
                            </div>

                            <div class="sm:w-44">
                                <x-select.styled wire:model="emails.{{ $index }}.type"
                                                 label="Art"
                                                 :options="$emailTypeOptions"
                                                 select="label:label|value:value" />
                            </div>

                            <div class="flex items-center gap-2 sm:pb-2">
                                <x-radio :checked="$email['is_primary']"
                                         label="Primär"
                                         wire:click="markEmailPrimary({{ $index }})" />

                                <x-button.circle color="red" outline icon="trash" sm
                                                 wire:click="removeEmail({{ $index }})" title="Entfernen" />
                            </div>
                        </div>
                    @endforeach

                    <x-button color="secondary" outline sm icon="plus" wire:click="addEmail">
                        E-Mail-Adresse hinzufügen
                    </x-button>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Telefonnummern</h2>
                </x-slot:header>

                <div class="space-y-3">
                    @foreach ($phones as $index => $phone)
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end" wire:key="phone-{{ $index }}">
                            <div class="flex-1">
                                <x-input wire:model="phones.{{ $index }}.number" label="Telefonnummer" />
                            </div>

                            <div class="sm:w-44">
                                <x-select.styled wire:model="phones.{{ $index }}.type"
                                                 label="Art"
                                                 :options="$phoneTypeOptions"
                                                 select="label:label|value:value" />
                            </div>

                            <div class="flex items-center gap-2 sm:pb-2">
                                <x-radio :checked="$phone['is_primary']"
                                         label="Primär"
                                         wire:click="markPhonePrimary({{ $index }})" />

                                <x-button.circle color="red" outline icon="trash" sm
                                                 wire:click="removePhone({{ $index }})" title="Entfernen" />
                            </div>
                        </div>
                    @endforeach

                    <x-button color="secondary" outline sm icon="plus" wire:click="addPhone">
                        Telefonnummer hinzufügen
                    </x-button>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Kundenzuordnungen</h2>
                        <p class="mt-1 text-xs text-ink-muted">
                            Mindestens ein Kunde ist erforderlich. Rollen, Priorität und Primärkennzeichen
                            gelten je Zuordnung.
                        </p>
                    </div>
                </x-slot:header>

                <x-errors only="assignments" title="Kundenzuordnung fehlt" class="mb-4" />

                <div class="space-y-4">
                    @foreach ($assignments as $index => $assignment)
                        <div class="rounded-md border border-line p-4"
                             wire:key="assignment-{{ $index }}">
                            <div class="grid gap-4 md:grid-cols-2">
                                <x-select.styled wire:model="assignments.{{ $index }}.customer_id"
                                                 label="Kunde"
                                                 placeholder="Bitte wählen"
                                                 :options="$customers"
                                                 select="label:label|value:id"
                                                 searchable
                                                 required />

                                <x-select.styled wire:model="assignments.{{ $index }}.role_ids"
                                                 label="Rollen"
                                                 placeholder="Keine Rolle"
                                                 :options="$roles"
                                                 select="label:name|value:id"
                                                 multiple
                                                 searchable />

                                <x-input wire:model="assignments.{{ $index }}.priority"
                                         type="number"
                                         min="1"
                                         max="999"
                                         label="Priorität"
                                         hint="Kleinere Werte stehen weiter oben." />

                                <x-select.styled wire:model="assignments.{{ $index }}.preferred_contact_method"
                                                 label="Bevorzugte Kontaktart für diesen Kunden"
                                                 placeholder="Wie beim Ansprechpartner"
                                                 :options="$contactMethodOptions"
                                                 select="label:label|value:value" />

                                <x-input wire:model="assignments.{{ $index }}.note"
                                         label="Notiz zur Zuordnung"
                                         class="md:col-span-2" />
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-4">
                                <x-toggle wire:model="assignments.{{ $index }}.is_primary_contact"
                                          label="Hauptansprechpartner"
                                          sm />

                                <x-toggle wire:model="assignments.{{ $index }}.is_billing_contact"
                                          label="Rechnungskontakt"
                                          sm />

                                <x-toggle wire:model="assignments.{{ $index }}.is_active" label="Aktiv" sm />

                                @if (count($assignments) > 1)
                                    <x-button color="red"
                                              outline
                                              sm
                                              icon="trash"
                                              class="ml-auto"
                                              wire:click="removeAssignment({{ $index }})">
                                        Zuordnung entfernen
                                    </x-button>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <x-button color="secondary" outline sm icon="plus" wire:click="addAssignment">
                        Weiteren Kunden zuordnen
                    </x-button>
                </div>
            </x-card>

            <div class="flex justify-end gap-2">
                <x-button color="secondary"
                          outline
                          :href="$this->isEditing() ? route('contacts.show', $contact) : route('contacts.index')"
                          wire:navigate>
                    Abbrechen
                </x-button>

                <x-button type="submit" wire:loading.attr="disabled">Speichern</x-button>
            </div>
        </form>
    </x-page>
</div>
