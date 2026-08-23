<div>
    <div class="mb-6">
        <a href="{{ $this->isEditing()
                ? route('customer-services.show', [$customer, $service])
                : route('customers.show', ['customer' => $customer, 'bereich' => 'leistungen']) }}"
           wire:navigate
           class="text-sm text-primary-600 hover:underline dark:text-primary-400">
            &larr; Zurück
        </a>

        <h1 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ $this->isEditing() ? 'Kundenleistung bearbeiten' : 'Kundenleistung anlegen' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $customer->customer_number }} · {{ $customer->displayName() }}
        </p>
    </div>

    <x-errors title="Die Kundenleistung konnte nicht gespeichert werden" class="mb-4" />

    <form wire:submit="save" class="space-y-6">
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Herkunft</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Optional. Ohne Katalogartikel entsteht eine vollständig individuelle Leistung.
                    </p>
                </div>
            </x-slot:header>

            <div class="grid gap-4 md:grid-cols-2">
                <x-select.styled wire:model.live="product_id"
                                 label="Katalogartikel"
                                 placeholder="Ohne Katalogartikel"
                                 :options="$products"
                                 select="label:name|value:id"
                                 searchable />

                <x-select.styled wire:model.live="product_variant_id"
                                 label="Variante"
                                 placeholder="Keine"
                                 :options="$variants"
                                 select="label:name|value:id"
                                 :disabled="$variants->isEmpty()" />
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Leistung</h2>
            </x-slot:header>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input wire:model="name" label="Interner Anzeigename" required />

                <x-input wire:model="billing_label"
                         label="ERP-/Rechnungsbezeichnung"
                         hint="Wird später für die Rechnungsstellung verwendet." />

                <x-textarea wire:model="description" label="Beschreibung" rows="3" class="md:col-span-2" />

                <x-select.styled wire:model.live="category_id"
                                 label="Kategorie"
                                 placeholder="Keine"
                                 :options="$rootCategories"
                                 select="label:name|value:id" />

                <x-select.styled wire:model="subcategory_id"
                                 label="Unterkategorie"
                                 placeholder="Keine"
                                 :options="$subcategories"
                                 select="label:name|value:id"
                                 :disabled="$subcategories->isEmpty()" />

                <x-select.styled wire:model="tagIds"
                                 label="Tags"
                                 placeholder="Keine"
                                 :options="$tags"
                                 select="label:name|value:id"
                                 multiple
                                 searchable />

                <x-select.styled wire:model="responsible_user_id"
                                 label="Interner Verantwortlicher"
                                 placeholder="Niemand"
                                 :options="$responsibleUsers"
                                 select="label:name|value:id" />

                <x-select.styled wire:model="status"
                                 label="Status"
                                 :options="$statusOptions"
                                 select="label:label|value:value"
                                 required />
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Preise und Abrechnung</h2>
            </x-slot:header>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input wire:model="purchase_price" label="Einkaufspreis" suffix="€" required />
                <x-input wire:model="sales_price" label="Verkaufspreis" suffix="€" required />

                <x-select.styled wire:model.live="billing_interval_unit"
                                 label="Abrechnungsintervall"
                                 :options="$intervalUnitOptions"
                                 select="label:label|value:value"
                                 required />

                @if ($this->requiresIntervalCount())
                    <x-input wire:model="billing_interval_count"
                             type="number"
                             min="1"
                             max="999"
                             label="Anzahl"
                             hint="Quartalsweise entspricht 3 Monaten." />
                @endif

                <x-date wire:model="service_start_date" label="Leistungsbeginn" format="DD.MM.YYYY" />

                <x-date wire:model="billing_start_date"
                        label="Abrechnungsstart"
                        format="DD.MM.YYYY"
                        hint="Kann vom Leistungsbeginn abweichen." />

                <x-date wire:model="first_billing_date"
                        label="Geplantes erstes Abrechnungsdatum"
                        format="DD.MM.YYYY" />
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Leistungsbestandteile</h2>
            </x-slot:header>

            <x-service-components-editor :components="$components" move-action="moveComponent" />
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button color="secondary"
                      outline
                      :href="$this->isEditing()
                          ? route('customer-services.show', [$customer, $service])
                          : route('customers.show', ['customer' => $customer, 'bereich' => 'leistungen'])"
                      wire:navigate>
                Abbrechen
            </x-button>

            <x-button type="submit" wire:loading.attr="disabled">Speichern</x-button>
        </div>
    </form>
</div>
