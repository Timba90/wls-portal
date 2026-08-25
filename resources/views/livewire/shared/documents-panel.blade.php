<div>
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-muted">
            Dateien liegen in privatem Speicher. Der Zugriff läuft ausschließlich über die Anwendung.
            Maximal {{ $maxSizeMb }} MB je Datei.
        </p>

        <x-button sm icon="arrow-up-tray" wire:click="create">Dokument hochladen</x-button>
    </div>

    <div class="divide-y divide-line">
        @forelse ($documents as $document)
            <div class="py-3" wire:key="document-{{ $document->id }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-ink">{{ $document->name }}</span>

                            <x-badge color="gray" :text="'Version '.$document->currentVersion?->version" sm />

                            @if ($document->isArchived())
                                <x-badge color="amber" text="Archiviert" sm />
                            @endif
                        </div>

                        @if ($document->description)
                            <p class="mt-1 text-sm text-ink-muted">{{ $document->description }}</p>
                        @endif

                        @if ($document->currentVersion)
                            <p class="mt-1 text-xs text-ink-muted">
                                {{ $document->currentVersion->original_filename }}
                                · {{ $document->currentVersion->humanSize() }}
                                · {{ $document->currentVersion->created_at?->format('d.m.Y H:i') }}
                                @if ($document->currentVersion->uploader)
                                    · {{ $document->currentVersion->uploader->name }}
                                @endif
                            </p>
                        @endif

                        @if ($document->versions->count() > 1)
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-accent">
                                    {{ $document->versions->count() }} Versionen anzeigen
                                </summary>

                                <ul class="mt-2 space-y-1 border-l border-line pl-3 text-xs text-ink-muted">
                                    @foreach ($document->versions as $version)
                                        <li wire:key="version-{{ $version->id }}">
                                            Version {{ $version->version }} ·
                                            {{ $version->original_filename }} ·
                                            {{ $version->humanSize() }} ·
                                            {{ $version->created_at?->format('d.m.Y H:i') }}
                                            @if ($version->uploader)
                                                · {{ $version->uploader->name }}
                                            @endif
                                            <a href="{{ route('documents.download', [$document, $version]) }}"
                                               class="ml-1 text-accent hover:underline">
                                                Herunterladen
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if ($document->currentVersion?->isPreviewable())
                            <x-button.circle color="secondary"
                                             outline
                                             icon="eye"
                                             sm
                                             :href="route('documents.preview', [$document, $document->currentVersion])"
                                             target="_blank"
                                             title="Vorschau" />
                        @endif

                        <x-button.circle color="secondary"
                                         outline
                                         icon="arrow-down-tray"
                                         sm
                                         :href="route('documents.download', [$document, $document->currentVersion])"
                                         title="Herunterladen" />

                        <x-button.circle color="secondary" outline icon="arrow-path" sm
                                         wire:click="addVersion({{ $document->id }})"
                                         title="Neue Version hochladen" />

                        @if ($document->isArchived())
                            <x-button.circle color="secondary" outline icon="arrow-uturn-left" sm
                                             wire:click="restore({{ $document->id }})"
                                             title="Archivierung aufheben" />
                        @else
                            <x-button.circle color="red" outline icon="archive-box" sm
                                             wire:click="archive({{ $document->id }})"
                                             title="Archivieren" />
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-ink-muted">
                Noch keine Dokumente hinterlegt.
            </p>
        @endforelse
    </div>

    <x-modal wire="showUploadForm"
             :id="'dokument-formular-'.$documentable->getKey()"
             :title="$newVersionForDocumentId ? 'Neue Version hochladen' : 'Dokument hochladen'"
             persistent>
        <x-errors title="Der Upload war nicht möglich" class="mb-4" />

        <div class="space-y-4">
            <x-upload wire:model="file" label="Datei" delete />

            <x-input wire:model="name"
                     label="Name"
                     :disabled="(bool) $newVersionForDocumentId"
                     placeholder="Wird aus dem Dateinamen übernommen" />

            <x-input wire:model="description" label="Beschreibung" placeholder="optional" />
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button color="secondary" outline wire:click="$set('showUploadForm', false)">Abbrechen</x-button>
                <x-button wire:click="upload" wire:loading.attr="disabled">Hochladen</x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    @script
    <script>
        $wire.on('dokument-gespeichert', () => $tsui.interaction('toast').success('Dokument gespeichert').send());
        $wire.on('dokument-archiviert', () => $tsui.interaction('toast').success('Dokument archiviert').send());
        $wire.on('dokument-reaktiviert', () => $tsui.interaction('toast').success('Archivierung aufgehoben').send());
    </script>
    @endscript
</div>
