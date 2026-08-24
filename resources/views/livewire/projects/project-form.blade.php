<div>
    <x-page title="{{ $this->isEditing() ? 'Projekt bearbeiten' : 'Projekt anlegen' }}"
            subtitle="{{ $this->isEditing()
                ? 'Projektnummer '.$project->project_number.' — nach der Erstellung unveränderlich.'
                : 'Ein Projekt gehört immer zu genau einem Kunden.' }}"
            back-label="Projekte ／ zurück zur Liste"
            back-url="{{ $this->isEditing() ? route('projects.show', $project) : route('projects.index') }}">

        <form wire:submit="save" class="space-y-6">
            <x-errors title="Das Projekt konnte nicht gespeichert werden" />

            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Stammdaten</h2>
                </x-slot:header>

                <div class="grid gap-4 md:grid-cols-2">
                    @if ($this->isEditing())
                        {{--
                            Der Kunde steht fest: ein Wechsel würde Positionen
                            aus Kundenleistungen an den falschen Vertrag hängen.
                        --}}
                        <div class="md:col-span-2">
                            <x-input label="Kunde"
                                     :value="$project->customer->customer_number.' · '.$project->customer->displayName()"
                                     disabled
                                     hint="Ein Projekt wechselt nicht den Kunden." />
                        </div>
                    @else
                        <div class="md:col-span-2">
                            <x-select.styled wire:model="customer_id"
                                             label="Kunde"
                                             placeholder="Bitte wählen"
                                             searchable
                                             :options="$customers"
                                             select="label:name|value:id"
                                             required />
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <x-input wire:model="name" label="Projektname" required />
                    </div>

                    <x-select.styled wire:model="project_type_id"
                                     label="Projekttyp"
                                     placeholder="Ohne Typ"
                                     :options="$projectTypes"
                                     select="label:name|value:id" />

                    <x-select.styled wire:model="responsible_user_id"
                                     label="Verantwortlich"
                                     placeholder="Niemand"
                                     :options="$responsibleUsers"
                                     select="label:name|value:id" />

                    <div class="md:col-span-2">
                        <x-textarea wire:model="description" label="Beschreibung" rows="3" />
                    </div>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Plan & Status</h2>
                </x-slot:header>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-select.styled wire:model="status"
                                     label="Status"
                                     :options="$statusOptions"
                                     select="label:label|value:value"
                                     hint="Archiviert wird ausschließlich über das Archivieren gesetzt."
                                     required />

                    <div></div>

                    <x-date wire:model="start_date" label="Beginn" format="DD.MM.YYYY" />

                    <x-date wire:model="deadline"
                            label="Deadline"
                            format="DD.MM.YYYY"
                            hint="Darf nicht vor dem Beginn liegen." />

                    <div class="md:col-span-2">
                        <x-textarea wire:model="risk_note"
                                    label="Risiko"
                                    rows="3"
                                    placeholder="Woran das Projekt scheitern könnte" />
                    </div>
                </div>
            </x-card>

            <div class="flex justify-end gap-2">
                <x-button color="secondary"
                          outline
                          :href="$this->isEditing() ? route('projects.show', $project) : route('projects.index')"
                          wire:navigate>
                    Abbrechen
                </x-button>

                <x-button type="submit">{{ $this->isEditing() ? 'Speichern' : 'Projekt anlegen' }}</x-button>
            </div>
        </form>
    </x-page>
</div>
