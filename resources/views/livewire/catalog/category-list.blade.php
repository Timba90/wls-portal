<div>
        <x-page title="Kategorien" subtitle="Eine Hierarchiestufe: Kategorie und Unterkategorie.">
        <x-slot:actions>
            <x-button icon="plus" wire:click="create">Kategorie anlegen</x-button>
        </x-slot:actions>


        <x-card>
            <div class="divide-y divide-line">
                @forelse ($categories as $category)
                    <div class="py-3" wire:key="category-{{ $category->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-medium text-ink">{{ $category->name }}</span>

                                @unless ($category->is_active)
                                    <x-badge color="gray" text="Inaktiv" sm class="ml-2" />
                                @endunless

                                <span class="ml-2 text-sm text-ink-faint">
                                    {{ $category->products_count }} Artikel
                                </span>

                                @if ($category->description)
                                    <p class="text-sm text-ink-muted">{{ $category->description }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 gap-2">
                                <x-button.circle color="secondary" outline icon="plus" sm
                                                 wire:click="create({{ $category->id }})"
                                                 title="Unterkategorie anlegen" />
                                <x-button.circle color="secondary" outline icon="pencil" sm
                                                 wire:click="edit({{ $category->id }})" title="Bearbeiten" />
                            </div>
                        </div>

                        @if ($category->children->isNotEmpty())
                            <div class="mt-2 space-y-1 border-l border-line pl-4">
                                @foreach ($category->children as $child)
                                    <div class="flex items-center justify-between gap-2"
                                         wire:key="category-child-{{ $child->id }}">
                                        <div>
                                            <span class="text-sm text-ink-base">{{ $child->name }}</span>

                                            @unless ($child->is_active)
                                                <x-badge color="gray" text="Inaktiv" sm class="ml-2" />
                                            @endunless
                                        </div>

                                        <x-button.circle color="secondary" outline icon="pencil" sm
                                                         wire:click="edit({{ $child->id }})" title="Bearbeiten" />
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-muted">
                        Noch keine Kategorien angelegt.
                    </p>
                @endforelse
            </div>
        </x-card>

        <x-modal wire="showForm" id="kategorie-formular" :title="$editingCategoryId ? 'Kategorie bearbeiten' : 'Kategorie anlegen'" persistent>
            <x-errors title="Kategorie konnte nicht gespeichert werden" class="mb-4" />

            <form wire:submit="save" class="space-y-4" id="category-form">
                <x-input wire:model="name" label="Name" required />
                <x-input wire:model="description" label="Beschreibung" />

                <x-select.styled wire:model="parent_id"
                                 label="Übergeordnete Kategorie"
                                 placeholder="Keine — Hauptkategorie"
                                 :options="$rootCategories"
                                 select="label:name|value:id"
                                 hint="Es ist genau eine Unterebene vorgesehen." />

                <x-input wire:model="sort_order" type="number" min="0" max="9999" label="Sortierung" />
                <x-toggle wire:model="is_active" label="Aktiv" />
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showForm', false)">Abbrechen</x-button>
                    <x-button type="submit" form="category-form">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('kategorie-gespeichert', () => $tsui.interaction('toast').success('Kategorie gespeichert').send());
        </script>
        @endscript
    </x-page>
</div>
