<div>
    <x-page title="{{ $this->isEditing() ? 'Domain bearbeiten' : 'Domain anlegen' }}"
            subtitle="{{ $this->isEditing()
                ? 'Von Hand gepflegt — es gibt keinen Anbieter, der diesen Stand liefert.'
                : 'Für Domains, deren Registrar hier keine Schnittstelle hat.' }}"
            back-label="Domains ／ zurück zur Liste"
            back-url="{{ $this->isEditing() ? route('domains.show', $domain) : route('domains.index') }}">

        <form wire:submit="save" class="space-y-6">
            <x-errors title="Die Domain konnte nicht gespeichert werden" />

            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Technischer Stand</h2>
                </x-slot:header>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-input wire:model="name"
                                 label="Domainname"
                                 placeholder="beispiel.de"
                                 required
                                 hint="Kleingeschrieben gespeichert. Eine Domain gibt es hier nur einmal." />
                    </div>

                    <x-input wire:model="status"
                             label="Status"
                             required
                             hint="Eigene Angabe — hier gibt kein Anbieter eine Schreibweise vor." />

                    <div class="flex items-end">
                        <x-toggle wire:model="auto_renew"
                                  label="Verlängert sich automatisch"
                                  hint="Nur zur Information; verlängert wird beim Registrar." />
                    </div>

                    <x-date wire:model="registered_on"
                            label="Registriert am"
                            format="DD.MM.YYYY" />

                    <x-date wire:model="expires_on"
                            label="Läuft ab am"
                            format="DD.MM.YYYY"
                            hint="Steht in der Liste und in den Kennzahlen." />

                    <div class="md:col-span-2">
                        <x-textarea wire:model="nameservers"
                                    label="Nameserver"
                                    rows="3"
                                    placeholder="ns1.example.net&#10;ns2.example.net"
                                    hint="Einer je Zeile. Leer lassen, wenn sie hier nicht geführt werden." />
                    </div>
                </div>
            </x-card>

            <div class="rounded-[8px] border border-line bg-raised px-3.5 py-3">
                <p class="text-[11.5px] text-ink-faint">
                    Der Kunde und die Kundenleistung werden nicht hier gesetzt, sondern über „Zuordnung" —
                    dieselbe Stelle wie beim importierten Bestand.
                    @if (! $this->isEditing())
                        Bei welchem Registrar die Domain liegt, gehört in eine Notiz oder ein eigenes Feld
                        auf der Detailseite.
                    @endif
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <x-button color="secondary"
                          outline
                          :href="$this->isEditing() ? route('domains.show', $domain) : route('domains.index')"
                          wire:navigate>
                    Abbrechen
                </x-button>

                <x-button type="submit">{{ $this->isEditing() ? 'Speichern' : 'Domain anlegen' }}</x-button>
            </div>
        </form>
    </x-page>
</div>
