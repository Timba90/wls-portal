<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="sm:w-56">
            <x-select.styled wire:model.live="filterCategory"
                             label="Kategorie"
                             placeholder="Alle"
                             :options="$categoryOptions"
                             select="label:label|value:value" />
        </div>

        <x-button sm icon="plus" wire:click="create">Notiz anlegen</x-button>
    </div>

    <div class="divide-y divide-line">
        @forelse ($notes as $note)
            <div class="py-3" wire:key="note-{{ $note->id }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :color="$note->category->color()" :text="$note->category->label()" sm />
                            <span class="text-xs text-ink-muted">
                                {{ $note->created_at->format('d.m.Y H:i') }}
                                @if ($note->user)
                                    · {{ $note->user->name }}
                                @endif
                                @if ($note->created_at->ne($note->updated_at))
                                    · bearbeitet {{ $note->updated_at->format('d.m.Y H:i') }}
                                @endif
                            </span>
                        </div>

                        <p class="mt-2 whitespace-pre-line text-sm text-ink-base">{{ $note->body }}</p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <x-button.circle color="secondary" outline icon="pencil" sm
                                         wire:click="edit({{ $note->id }})" title="Bearbeiten" />

                        <x-button.circle color="red"
                                         outline
                                         icon="trash"
                                         sm
                                         title="Löschen"
                                         x-on:click="$dialog.confirm({
                                             title: 'Notiz löschen?',
                                             description: 'Die Notiz wird endgültig entfernt.',
                                             accept: { text: 'Löschen', method: 'delete', params: {{ $note->id }} },
                                             reject: { text: 'Abbrechen' },
                                         })" />
                    </div>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-ink-muted">
                Noch keine Notizen erfasst.
            </p>
        @endforelse
    </div>

    <x-modal wire="showForm"
             :id="'notiz-formular-'.$notable->getKey()"
             :title="$editingNoteId ? 'Notiz bearbeiten' : 'Notiz anlegen'"
             persistent>
        <div class="space-y-4">
            <x-select.styled wire:model="category"
                             label="Kategorie"
                             :options="$categoryOptions"
                             select="label:label|value:value"
                             required />

            <x-textarea wire:model="body" label="Text" rows="6" required />
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button color="secondary" outline wire:click="$set('showForm', false)">Abbrechen</x-button>
                <x-button wire:click="save">Speichern</x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    @script
    <script>
        $wire.on('notiz-gespeichert', () => $tallstackui.toast().success('Notiz gespeichert').send());
        $wire.on('notiz-geloescht', () => $tallstackui.toast().success('Notiz gelöscht').send());
    </script>
    @endscript
</div>
