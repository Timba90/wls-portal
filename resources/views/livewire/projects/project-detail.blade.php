@php
    $reiter = collect([
        'plan' => 'Plan',
        'positionen' => 'Positionen',
        'notizen' => 'Notizen',
        'dokumente' => 'Dokumente',
        'felder' => 'Eigene Felder',
        'verlauf' => 'Verlauf',
    ]);

    $fortschritt = $project->progressPercentage();
    $tage = $project->daysUntilDeadline();
    $schreibgeschuetzt = $this->isReadOnly();
@endphp

<div>
    <x-page :title="$project->name"
            :subtitle="$project->project_number.' · '.$project->customer->displayName()"
            back-label="Projekte"
            :back-url="route('projects.index')">
        <x-slot:actions>
            @if ($project->isArchived())
                <x-button sm color="secondary" outline icon="arrow-uturn-left" wire:click="restore">
                    Archivierung aufheben
                </x-button>
            @else
                <x-button sm
                          color="secondary"
                          outline
                          icon="pencil"
                          :href="route('projects.edit', $project)"
                          wire:navigate>
                    Bearbeiten
                </x-button>

                <x-button sm color="secondary" outline icon="archive-box" wire:click="archive">
                    Archivieren
                </x-button>
            @endif
        </x-slot:actions>

        {{-- Kopfkarte: Kürzel, Name mit Status, Kunde und Kennzahlenreihe. --}}
        <div class="mb-3.5 flex flex-wrap items-center gap-4 rounded-[10px] border border-line bg-panel px-[17px] py-4">
            <x-avatar-initials :initials="$project->initials()" size="lg" />

            <div class="flex min-w-[210px] flex-[1_1_240px] flex-col gap-[5px]">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-[16px] font-semibold tracking-[-0.015em] text-ink">{{ $project->name }}</span>
                    <x-status-pill :kind="$project->status->pillKind()" :label="$project->status->label()" />

                    @if ($project->isOverdue())
                        <x-status-pill kind="bad" :label="abs($tage).' Tage überfällig'" :dot="false" />
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-[7px]">
                    <a href="{{ route('customers.show', $project->customer) }}"
                       wire:navigate
                       class="text-[11.5px] font-medium text-accent hover:underline">
                        {{ $project->customer->displayName() }}
                    </a>

                    <span class="text-[11.5px] text-ink-muted">
                        {{ $project->project_number }}
                        @if ($project->projectType)
                            · {{ $project->projectType->name }}
                        @endif
                    </span>
                </div>
            </div>

            <div class="ml-auto flex flex-wrap gap-[22px]">
                @foreach ([
                    ['Fortschritt', $fortschritt === null ? '—' : $fortschritt.'%'],
                    ['Volumen einmalig', $project->oneTimeVolume()->format()],
                    ['Laufend / Mon', $project->recurringVolume()->format()],
                    ['Deadline', $project->deadline?->format('d.m.Y') ?? '—'],
                ] as [$label, $wert])
                    <div class="flex flex-col gap-1" wire:key="kennzahl-{{ $loop->index }}">
                        <span class="text-[9.5px] font-semibold uppercase tracking-[0.09em] text-ink-faint">{{ $label }}</span>
                        <span class="tabular text-[15px] font-semibold text-ink">{{ $wert }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($schreibgeschuetzt)
            <div class="mb-3.5 rounded-[10px] border border-line bg-raised px-[17px] py-3 text-[12px] text-ink-muted">
                Dieses Projekt ist archiviert und schreibgeschützt. Heben Sie die Archivierung auf, um es zu ändern.
            </div>
        @endif

        <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-3.5">
                {{--
                    Die Reiter füllen die Breite, solange sie passen. Bei schmalen
                    Fenstern schrumpfen Flex-Elemente nicht unter ihre Textbreite —
                    dann scrollt die Leiste in sich, statt die Seite zu verbreitern.
                --}}
                <div class="flex gap-1 overflow-x-auto rounded-[9px] border border-line bg-panel p-1">
                    @foreach ($reiter as $schluessel => $beschriftung)
                        <button type="button"
                                wire:click="$set('tab', '{{ $schluessel }}')"
                                @class([
                                    'flex-1 whitespace-nowrap rounded-[6px] px-2.5 py-1.5 text-[12px] font-medium transition',
                                    'bg-accent text-accent-ink' => $tab === $schluessel,
                                    'text-ink-muted hover:bg-raised hover:text-ink-base' => $tab !== $schluessel,
                                ])>{{ $beschriftung }}</button>
                    @endforeach
                </div>

                @switch($tab)
                    @case('positionen')
                        <x-panel title="Positionen aus Katalog & Leistungen"
                                 subtitle="Katalogartikel, Kundenleistungen und frei erfasste Posten"
                                 :padded="false">
                            @unless ($schreibgeschuetzt)
                                <x-slot:header>
                                    <x-button sm color="secondary" outline icon="plus" wire:click="openPositionForm">
                                        Position
                                    </x-button>
                                </x-slot:header>
                            @endunless

                            @forelse ($project->positions as $position)
                                <div wire:key="position-{{ $position->id }}"
                                     class="grid items-center gap-3 border-b border-line px-4 py-3 last:border-b-0"
                                     style="grid-template-columns: minmax(0,2fr) 0.8fr 0.7fr 0.9fr 0.9fr auto">
                                    <div class="min-w-0">
                                        <p class="truncate text-[12.5px] font-medium text-ink-base">{{ $position->name }}</p>
                                        <p class="truncate text-[10.5px] text-ink-faint">{{ $position->source() }}</p>
                                    </div>

                                    <span class="text-[11.5px] text-ink-muted">{{ $position->kind->label() }}</span>

                                    <span class="tabular text-right text-[12px] text-ink-muted">
                                        {{ rtrim(rtrim(number_format((float) $position->quantity, 2, ',', '.'), '0'), ',') }}
                                    </span>

                                    <span class="tabular text-right text-[12px] text-ink-muted">
                                        {{ $position->unitPrice()->format() }}
                                    </span>

                                    <span class="tabular text-right text-[12.5px] font-medium text-ink">
                                        {{ $position->total()->format() }}
                                    </span>

                                    <div class="flex items-center gap-1">
                                        @unless ($schreibgeschuetzt)
                                            <x-button sm
                                                      color="secondary"
                                                      outline
                                                      icon="pencil"
                                                      title="Position bearbeiten"
                                                      wire:click="openPositionForm({{ $position->id }})" />

                                            <x-button sm
                                                      color="secondary"
                                                      outline
                                                      icon="trash"
                                                      title="Position entfernen"
                                                      wire:click="deletePosition({{ $position->id }})"
                                                      wire:confirm="Diese Position wirklich entfernen?" />
                                        @endunless
                                    </div>
                                </div>
                            @empty
                                <p class="px-4 py-[30px] text-center text-[12px] text-ink-faint">
                                    Noch keine Position erfasst.
                                </p>
                            @endforelse

                            {{-- Summenzeile: einmalig ist das Projektvolumen, Laufendes steht daneben. --}}
                            @if ($project->positions->isNotEmpty())
                                <div class="flex flex-wrap items-center justify-end gap-6 border-t border-line bg-raised px-4 py-3">
                                    <div class="flex items-baseline gap-2.5">
                                        <span class="text-[11px] text-ink-muted">Laufend / Mon</span>
                                        <span class="tabular text-[13px] text-ink-base">
                                            {{ $project->recurringVolume()->format() }}
                                        </span>
                                    </div>

                                    <div class="flex items-baseline gap-2.5">
                                        <span class="text-[11px] font-medium text-ink-muted">Projektvolumen</span>
                                        <span class="tabular text-[15px] font-semibold text-ink">
                                            {{ $project->oneTimeVolume()->format() }}
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </x-panel>
                        @break

                    @case('notizen')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.notes-panel :notable="$project" :key="'notizen-projekt-'.$project->id" />
                        </div>
                        @break

                    @case('dokumente')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.documents-panel :documentable="$project" :key="'dokumente-projekt-'.$project->id" />
                        </div>
                        @break

                    @case('felder')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:custom-fields.custom-fields-panel :record="$project"
                                                                        :read-only="$schreibgeschuetzt"
                                                                        :key="'felder-projekt-'.$project->id" />
                        </div>
                        @break

                    @case('verlauf')
                        <div class="rounded-[10px] border border-line bg-panel">
                            <div class="flex flex-col gap-[3px] border-b border-line px-[17px] py-[15px]">
                                <h3 class="text-[13.5px] font-semibold tracking-[-0.01em] text-ink">Verlauf</h3>
                                <span class="text-[11.5px] text-ink-faint">Änderungen an diesem Projekt</span>
                            </div>

                            <div class="p-[17px]">
                                <livewire:shared.audit-panel :auditable="$project" :key="'historie-projekt-'.$project->id" />
                            </div>
                        </div>
                        @break

                    @default
                        <x-panel title="Plan & Meilensteine"
                                 :subtitle="$fortschritt === null
                                     ? 'Ohne Meilensteine gibt es keinen messbaren Fortschritt'
                                     : $fortschritt.'% erledigt'"
                                 :padded="false">
                            @unless ($schreibgeschuetzt)
                                <x-slot:header>
                                    <x-button sm color="secondary" outline icon="plus" wire:click="openMilestoneForm">
                                        Meilenstein
                                    </x-button>
                                </x-slot:header>
                            @endunless

                            @if ($fortschritt !== null)
                                <div class="border-b border-line px-4 py-3">
                                    <x-progress :percent="$fortschritt" xs without-text color="primary" />
                                </div>
                            @endif

                            @forelse ($project->milestones as $meilenstein)
                                <div wire:key="meilenstein-{{ $meilenstein->id }}"
                                     class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3 last:border-b-0">
                                    <div class="min-w-[180px] flex-1">
                                        <p class="text-[12.5px] font-medium text-ink-base">{{ $meilenstein->name }}</p>

                                        @if ($meilenstein->note)
                                            <p class="text-[10.5px] text-ink-faint">{{ $meilenstein->note }}</p>
                                        @endif
                                    </div>

                                    <div class="flex min-w-[130px] flex-col">
                                        <span class="tabular text-[11.5px] text-ink-muted">
                                            {{ $meilenstein->due_date?->format('d.m.Y') ?? 'Ohne Termin' }}
                                        </span>

                                        @if ($meilenstein->dueLabel())
                                            <span @class([
                                                'text-[10.5px]',
                                                'text-[color:var(--pill-bad-ink)]' => $meilenstein->isOverdue(),
                                                'text-ink-faint' => ! $meilenstein->isOverdue(),
                                            ])>{{ $meilenstein->dueLabel() }}</span>
                                        @endif
                                    </div>

                                    <x-status-pill :kind="$meilenstein->status->pillKind()"
                                                   :label="$meilenstein->status->label()" />

                                    @unless ($schreibgeschuetzt)
                                        <div class="flex items-center gap-1">
                                            @unless ($meilenstein->status->countsAsSettled())
                                                <x-button sm
                                                          color="secondary"
                                                          outline
                                                          icon="check"
                                                          title="Als erledigt markieren"
                                                          wire:click="setMilestoneStatus({{ $meilenstein->id }}, 'done')" />
                                            @endunless

                                            <x-button sm
                                                      color="secondary"
                                                      outline
                                                      icon="pencil"
                                                      title="Meilenstein bearbeiten"
                                                      wire:click="openMilestoneForm({{ $meilenstein->id }})" />

                                            <x-button sm
                                                      color="secondary"
                                                      outline
                                                      icon="trash"
                                                      title="Meilenstein entfernen"
                                                      wire:click="deleteMilestone({{ $meilenstein->id }})"
                                                      wire:confirm="Diesen Meilenstein wirklich entfernen?" />
                                        </div>
                                    @endunless
                                </div>
                            @empty
                                <p class="px-4 py-[30px] text-center text-[12px] text-ink-faint">
                                    Noch kein Meilenstein geplant.
                                </p>
                            @endforelse
                        </x-panel>
                        @break
                @endswitch
            </div>

            {{-- Rechte Spalte: Stammdaten, Team, Status und Risiko. --}}
            <div class="flex min-w-0 flex-col gap-3.5">
                <x-panel title="Projektdaten">
                    <dl class="divide-y divide-line">
                        <x-detail-row label="Projektnummer" :value="$project->project_number" />
                        <x-detail-row label="Kunde" :value="$project->customer->displayName()" />
                        <x-detail-row label="Typ" :value="$project->projectType?->name" />
                        <x-detail-row label="Verantwortlich" :value="$project->responsibleUser?->name" />
                        <x-detail-row label="Beginn" :value="$project->start_date?->format('d.m.Y')" />
                        <x-detail-row label="Deadline" :value="$project->deadline?->format('d.m.Y')" />
                        <x-detail-row label="Angelegt" :value="$project->created_at?->format('d.m.Y')" />
                    </dl>

                    @if ($project->description)
                        <p class="mt-3 border-t border-line pt-3 text-[12px] leading-relaxed text-ink-muted">
                            {{ $project->description }}
                        </p>
                    @endif
                </x-panel>

                <x-panel title="Team" subtitle="Wer an diesem Projekt arbeitet" :padded="false">
                    @forelse ($project->members as $mitglied)
                        <div wire:key="mitglied-{{ $mitglied->id }}"
                             class="flex items-center gap-3 border-b border-line px-4 py-2.5 last:border-b-0">
                            <x-avatar-initials :initials="Str::upper(Str::substr($mitglied->user->name, 0, 2))" size="sm" />

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[12px] text-ink-base">{{ $mitglied->user->name }}</p>
                                <p class="truncate text-[10.5px] text-ink-faint">{{ $mitglied->role ?: 'Ohne Rolle' }}</p>
                            </div>

                            @unless ($schreibgeschuetzt)
                                <x-button sm
                                          color="secondary"
                                          outline
                                          icon="x-mark"
                                          title="Aus dem Team entfernen"
                                          wire:click="removeMember({{ $mitglied->id }})" />
                            @endunless
                        </div>
                    @empty
                        <p class="px-4 py-[22px] text-center text-[11.5px] text-ink-faint">
                            Noch niemand zugeordnet.
                        </p>
                    @endforelse

                    @unless ($schreibgeschuetzt)
                        <div class="flex flex-col gap-2 border-t border-line px-4 py-3">
                            <x-select.styled wire:model="newMemberUserId"
                                             label="Person"
                                             placeholder="Auswählen"
                                             :options="$availableUsers"
                                             select="label:name|value:id" />

                            <x-input wire:model="newMemberRole" label="Rolle" placeholder="z. B. Projektleitung" />

                            <x-button sm color="secondary" outline icon="plus" wire:click="addMember">
                                Zum Team hinzufügen
                            </x-button>
                        </div>
                    @endunless
                </x-panel>

                <x-panel title="Status & Risiko">
                    <dl class="divide-y divide-line">
                        <x-detail-row label="Status" :value="$project->status->label()" />
                        <x-detail-row label="Meilensteine" :value="$project->milestones->count()" />
                        <x-detail-row label="Positionen" :value="$project->positions->count()" />
                    </dl>

                    <p class="mt-3 border-t border-line pt-3 text-[12px] leading-relaxed text-ink-muted">
                        {{ $project->risk_note ?: 'Kein Risiko vermerkt.' }}
                    </p>
                </x-panel>
            </div>
        </div>

        {{-- Meilenstein anlegen oder ändern. --}}
        <x-modal wire="showMilestoneForm"
                 id="meilenstein-formular"
                 :title="$editingMilestoneId ? 'Meilenstein bearbeiten' : 'Meilenstein anlegen'"
                 size="lg"
                 persistent>
            <x-errors title="Der Meilenstein konnte nicht gespeichert werden" class="mb-4" />

            <div class="flex flex-col gap-3">
                <x-input wire:model="milestoneName" label="Bezeichnung" placeholder="z. B. Konzept abgenommen" />
                <x-input wire:model="milestoneNote" label="Notiz" placeholder="Optional" />

                <x-select.styled wire:model="milestoneStatus"
                                 label="Status"
                                 :options="$milestoneStatusOptions"
                                 select="label:label|value:value" />

                <x-date wire:model="milestoneDueDate" label="Termin" format="DD.MM.YYYY" />
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showMilestoneForm', false)">Abbrechen</x-button>
                    <x-button wire:click="saveMilestone">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        {{-- Position anlegen oder ändern. --}}
        <x-modal wire="showPositionForm"
                 id="position-formular"
                 :title="$editingPositionId ? 'Position bearbeiten' : 'Position anlegen'"
                 size="2xl"
                 persistent>
            <x-errors title="Die Position konnte nicht gespeichert werden" class="mb-4" />

            <div class="flex flex-col gap-3">
                <x-select.styled wire:model.live="positionSource"
                                 label="Herkunft"
                                 :options="[
                                     ['label' => 'Frei erfasst', 'value' => 'free'],
                                     ['label' => 'Aus Katalog', 'value' => 'catalog'],
                                     ['label' => 'Aus Kundenleistung', 'value' => 'service'],
                                 ]"
                                 select="label:label|value:value" />

                @if ($positionSource === 'catalog')
                    <x-select.styled wire:model.live="positionProductId"
                                     label="Katalogartikel"
                                     placeholder="Auswählen"
                                     searchable
                                     :options="$products"
                                     select="label:name|value:id" />
                @elseif ($positionSource === 'service')
                    <x-select.styled wire:model.live="positionServiceId"
                                     label="Kundenleistung"
                                     placeholder="Auswählen"
                                     searchable
                                     :options="$customerServices"
                                     select="label:name|value:id" />
                @endif

                <x-input wire:model="positionName" label="Bezeichnung" />

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-select.styled wire:model="positionKind"
                                     label="Art"
                                     :options="$positionKindOptions"
                                     select="label:label|value:value" />

                    <x-select.styled wire:model="positionStatus"
                                     label="Status"
                                     :options="$positionStatusOptions"
                                     select="label:label|value:value" />

                    <x-input wire:model="positionQuantity" label="Menge" />
                    <x-input wire:model="positionUnitPrice" label="Einzelpreis (EUR)" placeholder="0,00" />
                </div>

                <p class="text-[11px] text-ink-faint">
                    Name und Preis sind Vorschläge aus Katalog beziehungsweise Kundenleistung und bleiben frei änderbar —
                    ein Projekt darf vom Listenpreis abweichen.
                </p>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showPositionForm', false)">Abbrechen</x-button>
                    <x-button wire:click="savePosition">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('projekt-archiviert', () => $tallstackui.toast().success('Projekt archiviert').send());
            $wire.on('projekt-reaktiviert', () => $tallstackui.toast().success('Archivierung aufgehoben').send());
            $wire.on('meilenstein-gespeichert', () => $tallstackui.toast().success('Meilenstein gespeichert').send());
            $wire.on('meilenstein-geloescht', () => $tallstackui.toast().success('Meilenstein entfernt').send());
            $wire.on('position-gespeichert', () => $tallstackui.toast().success('Position gespeichert').send());
            $wire.on('position-geloescht', () => $tallstackui.toast().success('Position entfernt').send());
            $wire.on('team-gespeichert', () => $tallstackui.toast().success('Team ergänzt').send());
            $wire.on('team-entfernt', () => $tallstackui.toast().success('Aus dem Team entfernt').send());
        </script>
        @endscript
    </x-page>
</div>
