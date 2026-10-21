<div>
    <x-page title="{{ $this->isEditing() ? 'Artikel bearbeiten' : 'Artikel anlegen' }}"
            subtitle="Standardpreise und Abrechnungsintervall des Katalogartikels."
            back-label="Katalog ／ zurück"
            back-url="{{ $this->isEditing() ? route('products.show', $product) : route('products.index') }}">

        <x-errors title="Der Artikel konnte nicht gespeichert werden" class="mb-4" />

        <form wire:submit="save" class="space-y-6">
            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Stammdaten</h2>
                </x-slot:header>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-input wire:model="name" label="Name" required />

                    <x-input wire:model="internal_name"
                             label="Interne Bezeichnung"
                             hint="Für Suche und interne Zuordnung."
                             required />

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
                                     :disabled="$subcategories->isEmpty()"
                                     :hint="$category_id === '' ? 'Zuerst eine Kategorie wählen.' : null" />

                    <x-select.styled wire:model="status"
                                     label="Status"
                                     :options="$statusOptions"
                                     select="label:label|value:value" />
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Standardpreise und Abrechnung</h2>
                </x-slot:header>

                <div class="grid gap-4 md:grid-cols-2">
                    @if ($purchase_cost_model === 'per_service')
                        <x-input wire:model="default_purchase_price" label="Standard-Einkaufspreis" suffix="€" required />
                    @endif
                    <x-input wire:model="default_sales_price" label="Standard-Verkaufspreis" suffix="€" required />

                    <x-select.styled wire:model.live="default_billing_interval_unit"
                                     label="Abrechnungsintervall"
                                     :options="$intervalUnitOptions"
                                     select="label:label|value:value"
                                     required />

                    @if ($this->requiresIntervalCount())
                        <x-input wire:model.live.debounce.600ms="default_billing_interval_count"
                                 type="number"
                                 min="1"
                                 max="999"
                                 label="Anzahl"
                                 hint="Quartalsweise entspricht 3 Monaten." />
                    @endif
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Leistungsbestandteile</h2>
                        <p class="mt-1 text-xs text-ink-muted">
                            Strukturierte Bestandteile der Leistung. Preise sind optional.
                        </p>
                    </div>
                </x-slot:header>

                <x-service-components-editor :components="$components" move-action="moveComponent" />
            </x-card>

            <x-card>
                <x-slot:header><h2 class="text-sm font-semibold text-ink">Einkauf / Gemeinsame Kosten</h2></x-slot:header>
                <p class="mb-4 text-xs text-ink-muted">Einkaufsperiode und Lizenznutzung sind unabhängig von der Kundenabrechnung. Nur aktive Leistungen verbrauchen Lizenzen, auch ohne Abrechnung.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-select.styled wire:model.live="purchase_cost_model" label="Kostenmodell" :options="[['label' => 'Je Leistung (bisheriger Einkaufspreis)', 'value' => 'per_service'], ['label' => 'Je Nutzungseinheit', 'value' => 'per_unit'], ['label' => 'Gemeinsame unbegrenzte Lizenz', 'value' => 'shared_unlimited'], ['label' => 'Gemeinsames Lizenzpaket', 'value' => 'shared_pack']]" select="label:label|value:value" />
                    @if ($purchase_cost_model !== 'per_service')
                        <x-select.styled wire:model.live="purchase_usage_unit" label="Nutzungseinheit" :options="[['label' => 'Benutzer', 'value' => 'user'], ['label' => 'Kunde', 'value' => 'customer'], ['label' => 'Webseite', 'value' => 'website'], ['label' => 'Projekt', 'value' => 'project']]" select="label:label|value:value" />
                        @if ($purchase_usage_unit === 'project')
                            <p class="text-xs text-ink-muted md:col-span-2">Für jeden konkreten Webserver einen eigenen Artikel verwenden. So bleiben die Kosten verschiedener Server getrennt.</p>
                        @endif
                        <x-input wire:model="purchase_amount" label="Original-Einkaufsbetrag je Einheit / Paket" :suffix="$purchase_currency" required />
                        <x-select.styled wire:model.live="purchase_currency" label="Originalwährung" :options="[['label' => 'EUR', 'value' => 'EUR'], ['label' => 'USD', 'value' => 'USD']]" select="label:label|value:value" />
                        <x-select.styled wire:model="purchase_interval_unit" label="Einkaufsperiode" :options="[['label' => 'Monat', 'value' => 'month'], ['label' => 'Jahr', 'value' => 'year']]" select="label:label|value:value" />
                        <x-input wire:model="purchase_interval_count" type="number" min="1" max="999" label="Anzahl Einkaufsperioden" />
                        @if ($purchase_cost_model === 'shared_pack')
                            <x-input wire:model="purchase_units_per_pack" type="number" min="1" label="Einheiten je Paket" hint="Aufgerundete Paketanzahl, mindestens ein gekauftes Paket." required />
                        @endif
                        @if ($purchase_currency === 'USD')
                            <x-input wire:model="purchase_eur_amount" label="EUR-Kalkulationsbetrag je Einheit / Paket" suffix="€" hint="Optional. Vorläufige Kalkulation später durch Rechnungs- oder Bankbetrag ersetzen. Ohne EUR-Betrag bleiben Kosten und Marge unvollständig." />
                        @endif
                    @endif
                </div>
            </x-card>

            <div class="flex justify-end gap-2">
                <x-button color="secondary"
                          outline
                          :href="$this->isEditing() ? route('products.show', $product) : route('products.index')"
                          wire:navigate>
                    Abbrechen
                </x-button>

                <x-button type="submit" wire:loading.attr="disabled">Speichern</x-button>
            </div>
        </form>
    </x-page>
</div>
