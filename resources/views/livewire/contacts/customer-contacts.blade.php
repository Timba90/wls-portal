<div>
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-muted">
            {{ $assignments->count() }} {{ $assignments->count() === 1 ? 'Ansprechpartner' : 'Ansprechpartner' }}
            bei diesem Kunden.
        </p>

        <div class="flex flex-wrap gap-2">
            <x-button color="secondary" outline sm icon="users" wire:click="openDeputies">
                Vertretungen
            </x-button>

            <x-button sm
                      icon="plus"
                      :href="route('contacts.create', ['customerId' => $customer->id])"
                      wire:navigate>
                Ansprechpartner anlegen
            </x-button>
        </div>
    </div>

    <div class="divide-y divide-line">
        @forelse ($assignments as $assignment)
            <div class="py-3" wire:key="assignment-{{ $assignment->id }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('contacts.show', $assignment->contact) }}"
                               wire:navigate
                               class="font-medium text-accent hover:underline">
                                {{ $assignment->contact->fullName() }}
                            </a>

                            @if ($assignment->is_primary_contact)
                                <x-badge color="blue" text="Hauptansprechpartner" sm />
                            @endif

                            @if ($assignment->is_billing_contact)
                                <x-badge color="purple" text="Rechnungskontakt" sm />
                            @endif

                            @unless ($assignment->is_active)
                                <x-badge color="gray" text="Inaktiv" sm />
                            @endunless

                            @if ($assignment->contact->isArchived())
                                <x-badge color="gray" text="Archiviert" sm />
                            @endif
                        </div>

                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            @forelse ($assignment->roles as $role)
                                <x-badge color="gray" :text="$role->name" sm />
                            @empty
                                <span class="text-sm text-ink-faint">Keine Rolle zugewiesen</span>
                            @endforelse

                            <span class="ml-2 text-sm text-ink-muted">
                                Priorität {{ $assignment->priority }}
                            </span>
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

                    <div class="flex shrink-0 gap-2">
                        <x-button.circle color="secondary"
                                         outline
                                         icon="pencil"
                                         sm
                                         :href="route('contacts.edit', $assignment->contact)"
                                         wire:navigate
                                         title="Bearbeiten" />

                        <x-button.circle color="red"
                                         outline
                                         icon="x-mark"
                                         sm
                                         title="Zuordnung entfernen"
                                         x-on:click="$tsui.interaction('dialog')
                                             .question('Zuordnung entfernen?', 'Der Ansprechpartner bleibt erhalten und verliert nur die Verbindung zu diesem Kunden.')
                                             .wireable($wire.id)
                                             .confirm('Entfernen', 'detachAssignment', {{ $assignment->id }})
                                             .cancel('Abbrechen')
                                             .send()" />
                    </div>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-ink-muted">
                Diesem Kunden ist noch kein Ansprechpartner zugeordnet.
            </p>
        @endforelse
    </div>

    @if ($deputyGroups->isNotEmpty())
        <div class="mt-6 border-t border-line pt-4">
            <h3 class="mb-2 text-sm font-semibold text-ink">Vertretungen</h3>

            <div class="space-y-2">
                @foreach ($deputyGroups as $group)
                    <div class="text-sm" wire:key="deputy-group-{{ $group['role']->id }}">
                        <span class="font-medium text-ink-base">{{ $group['role']->name }}:</span>
                        <span class="text-ink-base">
                            {{ $group['deputies']->map(fn ($deputy) => $deputy->contact->fullName().' ('.$deputy->priority.')')->implode(', ') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-modal wire="showDeputies" id="vertretungen-formular" title="Vertretungen" size="2xl">
        <p class="mb-4 text-sm text-ink-muted">
            Je Rolle können mehrere Vertretungen mit Priorität hinterlegt werden.
            Kleinere Werte werden zuerst herangezogen.
        </p>

        <div class="space-y-3">
            @foreach ($deputies as $index => $deputy)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end" wire:key="deputy-{{ $index }}">
                    <div class="flex-1">
                        <x-select.styled wire:model="deputies.{{ $index }}.contact_role_id"
                                         label="Rolle"
                                         placeholder="Bitte wählen"
                                         :options="$roles"
                                         select="label:name|value:id" />
                    </div>

                    <div class="flex-1">
                        <x-select.styled wire:model="deputies.{{ $index }}.contact_id"
                                         label="Vertretung"
                                         placeholder="Bitte wählen"
                                         :options="$availableContacts"
                                         select="label:label|value:id" />
                    </div>

                    <div class="sm:w-28">
                        <x-input wire:model="deputies.{{ $index }}.priority"
                                 type="number"
                                 min="1"
                                 max="999"
                                 label="Priorität" />
                    </div>

                    <div class="sm:pb-2">
                        <x-button.circle color="red" outline icon="trash" sm
                                         wire:click="removeDeputy({{ $index }})" title="Entfernen" />
                    </div>
                </div>
            @endforeach

            <x-button color="secondary" outline sm icon="plus" wire:click="addDeputy">
                Vertretung hinzufügen
            </x-button>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button color="secondary" outline wire:click="$set('showDeputies', false)">Abbrechen</x-button>
                <x-button wire:click="saveDeputies">Speichern</x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    @script
    <script>
        $wire.on('vertretungen-gespeichert', () => $tsui.interaction('toast').success('Vertretungen gespeichert').send());
        $wire.on('zuordnung-entfernt', () => $tsui.interaction('toast').success('Zuordnung entfernt').send());
    </script>
    @endscript
</div>
