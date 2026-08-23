<div>
    <div class="mb-6">
        <a href="{{ route('products.index') }}"
           wire:navigate
           class="text-sm text-primary-600 hover:underline dark:text-primary-400">
            &larr; Zurück zum Katalog
        </a>

        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</h1>
                    <x-badge :color="$product->status->color()" :text="$product->status->label()" sm />

                    @foreach ($product->tags as $tag)
                        <x-badge :color="$tag->color" :text="$tag->name" sm />
                    @endforeach
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $product->internal_name }}
                    @if ($product->category)
                        · {{ $product->category->name }}@if ($product->subcategory) &rarr; {{ $product->subcategory->name }}@endif
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-button color="secondary" outline icon="pencil" :href="route('products.edit', $product)" wire:navigate>
                    Bearbeiten
                </x-button>

                @if ($product->isArchived())
                    <x-button color="secondary" outline icon="arrow-uturn-left" wire:click="restore">
                        Archivierung aufheben
                    </x-button>
                @else
                    <x-button color="red"
                              outline
                              icon="archive-box"
                              x-on:click="$dialog.confirm({
                                  title: 'Artikel archivieren?',
                                  description: 'Der Artikel und seine Varianten werden archiviert. Bestehende Kundenleistungen bleiben unverändert.',
                                  accept: { text: 'Archivieren', method: 'archive' },
                                  reject: { text: 'Abbrechen' },
                              })">
                        Archivieren
                    </x-button>
                @endif
            </div>
        </div>
    </div>

    @if (session('erfolg'))
        <x-alert color="green" class="mb-4">{{ session('erfolg') }}</x-alert>
    @endif

    @if ($product->isArchived())
        <x-alert color="amber" class="mb-4" title="Archivierter Artikel">
            Bestehende Kundenleistungen bleiben unverändert bestehen.
        </x-alert>
    @endif

    <x-tab wire:model="tab">
        <x-tab.items tab="uebersicht" title="Übersicht">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-card>
                    <x-slot:header>
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Stammdaten</h2>
                    </x-slot:header>

                    <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
                        <x-detail-row label="Name" :value="$product->name" />
                        <x-detail-row label="Interne Bezeichnung" :value="$product->internal_name" />
                        <x-detail-row label="Kategorie" :value="$product->category?->name" />
                        <x-detail-row label="Unterkategorie" :value="$product->subcategory?->name" />
                        <x-detail-row label="Status" :value="$product->status->label()" />
                    </dl>

                    @if ($product->description)
                        <p class="mt-4 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">
                            {{ $product->description }}
                        </p>
                    @endif
                </x-card>

                <x-card>
                    <x-slot:header>
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Standardpreise</h2>
                    </x-slot:header>

                    <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
                        <x-detail-row label="Einkaufspreis" :value="$product->defaultPurchasePrice()->format()" />
                        <x-detail-row label="Verkaufspreis" :value="$product->defaultSalesPrice()->format()" />
                        <x-detail-row label="Marge" :value="$product->defaultMargin()->format()
                            .($product->defaultMarginPercentage() !== null
                                ? ' ('.number_format($product->defaultMarginPercentage(), 1, ',', '.').' %)'
                                : '')" />
                        <x-detail-row label="Abrechnungsintervall" :value="$product->defaultBillingInterval()->label()" />

                        @if ($product->defaultBillingInterval()->isRecurring())
                            <x-detail-row label="Monatswert (Verkauf)"
                                          :value="$product->defaultBillingInterval()->toMonthly($product->defaultSalesPrice())->format()" />
                            <x-detail-row label="Jahreswert (Verkauf)"
                                          :value="$product->defaultBillingInterval()->toYearly($product->defaultSalesPrice())->format()" />
                        @endif
                    </dl>
                </x-card>
            </div>

            <x-card class="mt-4">
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Leistungsbestandteile</h2>
                </x-slot:header>

                <x-service-components-list :components="$product->serviceComponents" />
            </x-card>

            <x-card class="mt-4">
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Benutzerdefinierte Felder</h2>
                </x-slot:header>

                <livewire:custom-fields.custom-fields-panel :record="$product"
                                                            :read-only="$product->isArchived()"
                                                            :key="'felder-artikel-'.$product->id" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="historie" title="Historie">
            <x-card>
                <livewire:shared.audit-panel :auditable="$product" :key="'historie-artikel-'.$product->id" />
            </x-card>
        </x-tab.items>

        <x-tab.items tab="varianten" title="Varianten">
            <x-card>
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Varianten übernehmen leere Preis- und Intervallangaben vom Artikel.
                    </p>

                    <x-button sm icon="plus" wire:click="createVariant">Variante anlegen</x-button>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-dark-600">
                    @forelse ($product->variants as $variant)
                        <div class="py-3" wire:key="variant-{{ $variant->id }}">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $variant->name }}</span>
                                        <x-badge :color="$variant->status->color()" :text="$variant->status->label()" sm />

                                        @if ($variant->overridesProductDefaults())
                                            <x-badge color="blue" text="Eigene Werte" sm />
                                        @endif
                                    </div>

                                    @if ($variant->description)
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $variant->description }}</p>
                                    @endif

                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        Einkauf {{ $variant->effectivePurchasePrice()->format() }}
                                        · Verkauf {{ $variant->effectiveSalesPrice()->format() }}
                                        · Marge {{ $variant->effectiveMargin()->format() }}
                                        · {{ $variant->effectiveBillingInterval()->label() }}
                                    </p>

                                    @if ($variant->serviceComponents->isNotEmpty())
                                        <ul class="mt-2 list-inside list-disc text-sm text-gray-500 dark:text-gray-400">
                                            @foreach ($variant->serviceComponents as $component)
                                                <li>{{ $component->title }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <div class="flex shrink-0 gap-2">
                                    <x-button.circle color="secondary" outline icon="pencil" sm
                                                     wire:click="editVariant({{ $variant->id }})" title="Bearbeiten" />

                                    @if ($variant->isArchived())
                                        <x-button.circle color="secondary" outline icon="arrow-uturn-left" sm
                                                         wire:click="restoreVariant({{ $variant->id }})"
                                                         title="Archivierung aufheben" />
                                    @else
                                        <x-button.circle color="red" outline icon="archive-box" sm
                                                         wire:click="archiveVariant({{ $variant->id }})"
                                                         title="Archivieren" />
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Dieser Artikel besitzt keine Varianten.
                        </p>
                    @endforelse
                </div>
            </x-card>
        </x-tab.items>
    </x-tab>

    <x-modal wire="showVariantForm" id="varianten-formular"
             :title="$editingVariantId ? 'Variante bearbeiten' : 'Variante anlegen'"
             size="2xl"
             persistent>
        <x-errors title="Die Variante konnte nicht gespeichert werden" class="mb-4" />

        <div class="space-y-4">
            <x-input wire:model="variantName" label="Name" required />
            <x-textarea wire:model="variantDescription" label="Beschreibung" rows="2" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input wire:model="variantPurchasePrice"
                         label="Einkaufspreis"
                         suffix="€"
                         placeholder="Wie Artikel"
                         hint="Leer lassen, um den Artikelwert zu übernehmen." />

                <x-input wire:model="variantSalesPrice"
                         label="Verkaufspreis"
                         suffix="€"
                         placeholder="Wie Artikel"
                         hint="Leer lassen, um den Artikelwert zu übernehmen." />

                <x-select.styled wire:model.live="variantIntervalUnit"
                                 label="Abrechnungsintervall"
                                 placeholder="Wie Artikel"
                                 :options="$intervalUnitOptions"
                                 select="label:label|value:value" />

                @if ($variantIntervalUnit !== '' && $variantIntervalUnit !== 'once')
                    <x-input wire:model="variantIntervalCount" type="number" min="1" max="999" label="Anzahl" />
                @endif

                <x-input wire:model="variantSortOrder" type="number" min="0" max="9999" label="Sortierung" />

                <x-select.styled wire:model="variantStatus"
                                 label="Status"
                                 :options="$statusOptions"
                                 select="label:label|value:value" />
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-100">Leistungsbestandteile</p>

                <x-service-components-editor :components="$variantComponents"
                                             add-action="addVariantComponent"
                                             remove-action="removeVariantComponent"
                                             state-path="variantComponents" />
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button color="secondary" outline wire:click="$set('showVariantForm', false)">Abbrechen</x-button>
                <x-button wire:click="saveVariant">Speichern</x-button>
            </div>
        </x-slot:footer>
    </x-modal>

    @script
    <script>
        $wire.on('artikel-archiviert', () => $tallstackui.toast().success('Artikel archiviert').send());
        $wire.on('artikel-reaktiviert', () => $tallstackui.toast().success('Archivierung aufgehoben').send());
        $wire.on('variante-gespeichert', () => $tallstackui.toast().success('Variante gespeichert').send());
        $wire.on('variante-archiviert', () => $tallstackui.toast().success('Variante archiviert').send());
        $wire.on('variante-reaktiviert', () => $tallstackui.toast().success('Variante reaktiviert').send());
    </script>
    @endscript
</div>
