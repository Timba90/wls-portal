<div>
    <div class="mb-6">
        <a href="{{ route('customers.index') }}"
           wire:navigate
           class="text-sm text-primary-600 hover:underline dark:text-primary-400">
            &larr; Zurück zur Kundenliste
        </a>

        <h1 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ $this->isEditing() ? 'Kunde bearbeiten' : 'Kunde anlegen' }}
        </h1>

        @if ($this->isEditing())
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Kundennummer {{ $customer->customer_number }} — nach der Erstellung unveränderlich.
            </p>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Stammdaten</h2>
            </x-slot:header>

            <div class="grid gap-4 md:grid-cols-2">
                <x-select.styled wire:model.live="type"
                                 label="Kundentyp"
                                 :options="$typeOptions"
                                 select="label:label|value:value"
                                 :disabled="$this->isEditing()"
                                 :hint="$this->isEditing() ? 'Der Kundentyp kann nachträglich nicht gewechselt werden.' : null"
                                 required />

                <div></div>

                @if ($this->isPrivate())
                    <x-select.styled wire:model="salutation"
                                     label="Anrede"
                                     placeholder="Bitte wählen"
                                     :options="$salutationOptions"
                                     select="label:label|value:value" />

                    <x-input wire:model="academic_title" label="Akademischer Titel" placeholder="z. B. Dr." />

                    <x-input wire:model="first_name" label="Vorname" required />
                    <x-input wire:model="last_name" label="Nachname" required />

                    <x-date wire:model="birth_date" label="Geburtsdatum" format="DD.MM.YYYY" />

                    <x-select.styled wire:model="gender"
                                     label="Geschlecht"
                                     placeholder="Bitte wählen"
                                     :options="$genderOptions"
                                     select="label:label|value:value" />
                @else
                    <x-input wire:model="company_name" label="Firmenname" required class="md:col-span-2" />
                @endif

                <x-input wire:model="short_label"
                         label="Kurzbezeichnung"
                         hint="Muss nicht eindeutig sein."
                         required />

                <x-input wire:model="internal_code"
                         label="Internes Kürzel"
                         hint="Muss nicht eindeutig sein."
                         required />

                <x-select.styled wire:model="responsible_user_id"
                                 label="Interner Verantwortlicher"
                                 placeholder="Niemand"
                                 :options="$responsibleUsers"
                                 select="label:name|value:id" />
            </div>
        </x-card>

        @if ($this->isPrivate())
            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">E-Mail-Adressen</h2>
                </x-slot:header>

                <div class="space-y-3">
                    @foreach ($emails as $index => $email)
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end" wire:key="email-{{ $index }}">
                            <div class="flex-1">
                                <x-input wire:model="emails.{{ $index }}.email"
                                         type="email"
                                         label="E-Mail-Adresse" />
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

                                <x-button.circle color="red"
                                                 outline
                                                 icon="trash"
                                                 sm
                                                 wire:click="removeEmail({{ $index }})"
                                                 title="Entfernen" />
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
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Telefonnummern</h2>
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

                                <x-button.circle color="red"
                                                 outline
                                                 icon="trash"
                                                 sm
                                                 wire:click="removePhone({{ $index }})"
                                                 title="Entfernen" />
                            </div>
                        </div>
                    @endforeach

                    <x-button color="secondary" outline sm icon="plus" wire:click="addPhone">
                        Telefonnummer hinzufügen
                    </x-button>
                </div>
            </x-card>
        @endif

        <div class="flex justify-end gap-2">
            <x-button color="secondary"
                      outline
                      :href="$this->isEditing() ? route('customers.show', $customer) : route('customers.index')"
                      wire:navigate>
                Abbrechen
            </x-button>

            <x-button type="submit" wire:loading.attr="disabled">Speichern</x-button>
        </div>
    </form>
</div>
