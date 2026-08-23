<div>
    <x-page title="{{ $service->name }}"
            subtitle="{{ $customer->customer_number }} · {{ $customer->displayName() }}"
            back-label="Leistungen ／ zurück zum Kunden"
            back-url="{{ route('customers.show', ['customer' => $customer, 'bereich' => 'leistungen']) }}">
        <x-slot:actions>
            @if ($service->isArchived())
            <x-button sm color="secondary" outline icon="arrow-uturn-left" wire:click="restore">
                Archivierung aufheben
            </x-button>
                @else
            <x-button sm color="secondary"
                      outline
                      icon="pencil"
                      :href="route('customer-services.edit', [$customer, $service])"
                      wire:navigate>
                Bearbeiten
            </x-button>

            @if ($service->do_not_bill)
                <x-button sm color="secondary" outline icon="banknotes" wire:click="releaseDoNotBill">
                    Wieder abrechnen
                </x-button>
            @else
                <x-button sm color="secondary" outline icon="no-symbol" wire:click="$set('showDoNotBillForm', true)">
                    Bewusst nicht abrechnen
                </x-button>
            @endif

            <x-button sm color="red"
                      outline
                      icon="archive-box"
                      x-on:click="$dialog.confirm({
                          title: 'Leistung archivieren?',
                          description: 'Archivierte Leistungen sind vollständig schreibgeschützt und bleiben historisch erhalten.',
                          accept: { text: 'Archivieren', method: 'archive' },
                          reject: { text: 'Abbrechen' },
                      })">
                Archivieren
            </x-button>
                @endif
        </x-slot:actions>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <x-badge :color="$service->status->color()" :text="$service->status->label()" sm />

            @if ($service->do_not_bill)
                <x-badge color="amber" :text="'Nicht abrechnen · '.$service->do_not_bill_reason?->label()" sm />
            @endif

            @foreach ($service->tags as $tag)
                <x-badge :color="$tag->color" :text="$tag->name" sm />
            @endforeach
        </div>

        @if (session('erfolg'))
            <x-alert color="green" class="mb-4">{{ session('erfolg') }}</x-alert>
        @endif

        @if ($service->isArchived())
            <x-alert color="amber" class="mb-4" title="Archivierte Leistung">
                Diese Leistung ist schreibgeschützt. Sie bleibt historisch erhalten und kann nicht mehr verändert werden.
            </x-alert>
        @endif

        @if ($service->do_not_bill)
            <x-alert color="amber" class="mb-4" title="Bewusst nicht abrechnen">
                Grund: {{ $service->do_not_bill_reason?->label() }}.
                Gesetzt am {{ $service->do_not_bill_since?->format('d.m.Y H:i') }}.
                Die Kennzeichnung gilt, bis sie manuell entfernt wird — es erfolgt keine rückwirkende Nachberechnung.
            </x-alert>
        @elseif ($service->do_not_bill_released_at)
            <x-alert color="blue" class="mb-4">
                Die Kennzeichnung „Bewusst nicht abrechnen" wurde am
                {{ $service->do_not_bill_released_at->format('d.m.Y H:i') }} entfernt.
                Die normale Betrachtung beginnt ab diesem Zeitpunkt.
            </x-alert>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Leistung</h2>
                </x-slot:header>

                <dl class="divide-y divide-line text-sm">
                    <x-detail-row label="Interner Anzeigename" :value="$service->name" />
                    <x-detail-row label="Rechnungsbezeichnung" :value="$service->billing_label" />
                    <x-detail-row label="Status" :value="$service->status->label()" />
                    <x-detail-row label="Kategorie" :value="$service->category?->name" />
                    <x-detail-row label="Unterkategorie" :value="$service->subcategory?->name" />
                    <x-detail-row label="Interner Verantwortlicher" :value="$service->responsibleUser?->name" />
                    <x-detail-row label="Leistungsbeginn" :value="$service->service_start_date?->format('d.m.Y')" />
                    <x-detail-row label="Abrechnungsstart" :value="$service->billing_start_date?->format('d.m.Y')" />
                    <x-detail-row label="Erstes Abrechnungsdatum" :value="$service->first_billing_date?->format('d.m.Y')" />
                </dl>

                @if ($service->description)
                    <p class="mt-4 whitespace-pre-line text-sm text-ink-base">
                        {{ $service->description }}
                    </p>
                @endif
            </x-card>

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-ink">Preise</h2>

                        @unless ($service->isArchived())
                            <x-button color="secondary" outline sm icon="arrow-trending-up"
                                      wire:click="openPriceChangeForm('sales')">
                                Preisänderung
                            </x-button>
                        @endunless
                    </div>
                </x-slot:header>

                <dl class="divide-y divide-line text-sm">
                    <x-detail-row label="Einkaufspreis" :value="$service->purchasePrice()->format()" />
                    <x-detail-row label="Verkaufspreis" :value="$service->salesPrice()->format()" />
                    <x-detail-row label="Marge / Deckungsbeitrag"
                                  :value="$service->margin()->format()
                                      .($service->marginPercentage() !== null
                                          ? ' ('.number_format($service->marginPercentage(), 1, ',', '.').' %)'
                                          : '')" />
                    <x-detail-row label="Abrechnungsintervall" :value="$service->billingInterval()->label()" />

                    @if ($service->billingInterval()->isRecurring())
                        <x-detail-row label="Monatsumsatz" :value="$service->monthlyRevenue()->format()" />
                        <x-detail-row label="Jahresumsatz" :value="$service->yearlyRevenue()->format()" />
                        <x-detail-row label="Monatliche Kosten" :value="$service->monthlyCosts()->format()" />
                        <x-detail-row label="Monatliche Marge" :value="$service->monthlyMargin()->format()" />
                    @else
                        <x-detail-row label="Monats- und Jahreswert"
                                      value="Einmalige Leistungen fließen nicht in wiederkehrende Kennzahlen ein." />
                    @endif
                </dl>
            </x-card>
        </div>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Herkunft aus dem Katalog</h2>
            </x-slot:header>

            @if ($service->isFromCatalog())
                <dl class="divide-y divide-line text-sm">
                    <x-detail-row label="Katalogartikel" :value="$service->product?->name" />
                    <x-detail-row label="Variante" :value="$service->productVariant?->name" />
                </dl>

                @if ($deviations !== [])
                    <div class="mt-4">
                        <p class="mb-2 text-sm font-medium text-ink-base">
                            Abweichungen vom Katalogstand bei Verknüpfung
                        </p>

                        <div class="divide-y divide-line text-sm">
                            @foreach ($deviations as $feld => $werte)
                                <div class="grid grid-cols-3 gap-4 py-2" wire:key="deviation-{{ $loop->index }}">
                                    <span class="text-ink-muted">{{ $feld }}</span>
                                    <span class="text-ink-muted line-through">{{ $werte['katalog'] }}</span>
                                    <span class="font-medium text-ink">{{ $werte['kunde'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="mt-4 text-sm text-ink-muted">
                        Keine Abweichungen gegenüber dem Katalogstand bei Verknüpfung.
                    </p>
                @endif
            @else
                <p class="text-sm text-ink-muted">
                    Vollständig individuelle Leistung ohne Katalogartikel.
                </p>
            @endif
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Leistungsbestandteile</h2>
            </x-slot:header>

            <x-service-components-list :components="$service->serviceComponents" />
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Benutzerdefinierte Felder</h2>
            </x-slot:header>

            <livewire:custom-fields.custom-fields-panel :record="$service"
                                                        :read-only="$service->isArchived()"
                                                        :key="'felder-leistung-'.$service->id" />
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Notizen</h2>
            </x-slot:header>

            <livewire:shared.notes-panel :notable="$service" :key="'notizen-leistung-'.$service->id" />
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Dokumente</h2>
            </x-slot:header>

            <livewire:shared.documents-panel :documentable="$service" :key="'dokumente-leistung-'.$service->id" />
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-ink">Historie</h2>
            </x-slot:header>

            <livewire:shared.audit-panel :auditable="$service" :key="'historie-leistung-'.$service->id" />
        </x-card>

        <x-card class="mt-4">
            <x-slot:header>
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Preisverlauf</h2>
                        <p class="mt-1 text-xs text-ink-muted">
                            Preise werden nie überschrieben. Rückwirkende Änderungen sind ausgeschlossen,
                            mehrere zukünftige Änderungen dürfen nebeneinander geplant werden.
                        </p>
                    </div>

                    @unless ($service->isArchived())
                        <x-button color="secondary" outline sm icon="plus"
                                  wire:click="openPriceChangeForm('sales')">
                            Preisänderung planen
                        </x-button>
                    @endunless
                </div>
            </x-slot:header>

            @if ($scheduledPriceChanges->isNotEmpty())
                <div class="mb-4">
                    <p class="mb-2 text-sm font-medium text-ink-base">Geplante Änderungen</p>

                    <div class="divide-y divide-line">
                        @foreach ($scheduledPriceChanges as $change)
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2"
                                 wire:key="scheduled-{{ $change->id }}">
                                <div class="text-sm">
                                    <span class="font-medium text-ink">
                                        {{ $change->price_type->label() }}
                                    </span>
                                    <span class="text-ink-muted">
                                        {{ $change->oldPrice()?->format() ?? '—' }}
                                        &rarr;
                                    </span>
                                    <span class="font-medium tabular-nums text-ink">
                                        {{ $change->newPrice()->format() }}
                                    </span>
                                    <span class="text-ink-muted">
                                        ab {{ $change->effective_date->format('d.m.Y') }}
                                    </span>

                                    @if ($change->note)
                                        <span class="text-ink-faint">· {{ $change->note }}</span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-badge color="blue" text="Geplant" sm />

                                    @unless ($service->isArchived())
                                        <x-button.circle color="red"
                                                         outline
                                                         icon="trash"
                                                         sm
                                                         title="Geplante Preisänderung löschen"
                                                         x-on:click="$dialog.confirm({
                                                             title: 'Geplante Preisänderung löschen?',
                                                             description: 'Die Änderung wird zum Wirksamkeitsdatum nicht mehr greifen.',
                                                             accept: { text: 'Löschen', method: 'cancelPriceChange', params: {{ $change->id }} },
                                                             reject: { text: 'Abbrechen' },
                                                         })" />
                                    @endunless
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <p class="mb-2 text-sm font-medium text-ink-base">Wirksam gewordene Änderungen</p>

            <div class="divide-y divide-line">
                @forelse ($appliedPriceChanges as $change)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2"
                         wire:key="applied-{{ $change->id }}">
                        <div class="text-sm">
                            <span class="font-medium text-ink">
                                {{ $change->price_type->label() }}
                            </span>
                            <span class="text-ink-muted">
                                {{ $change->oldPrice()?->format() ?? 'neu' }}
                                &rarr;
                            </span>
                            <span class="font-medium tabular-nums text-ink">
                                {{ $change->newPrice()->format() }}
                            </span>

                            @if ($change->difference())
                                <span class="tabular-nums {{ $change->difference()->isNegative() ? 'text-[color:var(--pill-bad-ink)]' : 'text-[color:var(--pill-ok-ink)]' }}">
                                    ({{ $change->difference()->isNegative() ? '' : '+' }}{{ $change->difference()->format() }})
                                </span>
                            @endif

                            @if ($change->note)
                                <span class="text-ink-faint">· {{ $change->note }}</span>
                            @endif
                        </div>

                        <div class="text-xs text-ink-muted">
                            wirksam ab {{ $change->effective_date->format('d.m.Y') }}
                            · erfasst {{ $change->applied_at?->format('d.m.Y H:i') }}
                            @if ($change->user)
                                · {{ $change->user->name }}
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-ink-muted">
                        Noch keine Preisänderungen erfasst.
                    </p>
                @endforelse
            </div>
        </x-card>

        @unless ($service->isArchived())
            <x-card class="mt-4">
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-ink">Status wechseln</h2>
                </x-slot:header>

                <div class="flex flex-wrap gap-2">
                    @foreach ($statusOptions as $option)
                        <x-button sm
                                  :color="$option['value'] === $service->status->value ? 'primary' : 'secondary'"
                                  :outline="$option['value'] !== $service->status->value"
                                  wire:click="changeStatus('{{ $option['value'] }}')">
                            {{ $option['label'] }}
                        </x-button>
                    @endforeach
                </div>
            </x-card>
        @endunless

        <x-modal wire="showDoNotBillForm" id="nicht-abrechnen-formular" title="Bewusst nicht abrechnen" persistent>
            <p class="mb-4 text-sm text-ink-muted">
                Die Kennzeichnung gilt, bis sie manuell entfernt wird. Nach dem Entfernen beginnt die
                normale Betrachtung erst ab diesem Zeitpunkt — es erfolgt keine rückwirkende Nachberechnung.
            </p>

            <x-select.styled wire:model="doNotBillReason"
                             label="Grund"
                             :options="$doNotBillReasonOptions"
                             select="label:label|value:value"
                             required />

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showDoNotBillForm', false)">Abbrechen</x-button>
                    <x-button wire:click="markDoNotBill">Kennzeichnen</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        <x-modal wire="showPriceChangeForm" id="preisaenderung-formular" title="Preisänderung" persistent>
            <x-errors title="Die Preisänderung konnte nicht gespeichert werden" class="mb-4" />

            <div class="space-y-4">
                <x-select.styled wire:model="priceChangeType"
                                 label="Preisart"
                                 :options="$priceTypeOptions"
                                 select="label:label|value:value"
                                 required />

                <x-input wire:model="priceChangeValue" label="Neuer Preis" suffix="€" required />

                <x-date wire:model="priceChangeEffectiveDate"
                        label="Wirksam ab"
                        format="DD.MM.YYYY"
                        :min-date="now()->toDateString()"
                        hint="Heute wirkt sofort. Rückwirkende Änderungen sind nicht möglich." />

                <x-input wire:model="priceChangeNote" label="Notiz" placeholder="optional" />
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showPriceChangeForm', false)">Abbrechen</x-button>
                    <x-button wire:click="savePriceChange">Speichern</x-button>
                </div>
            </x-slot:footer>
        </x-modal>

        @script
        <script>
            $wire.on('status-geaendert', () => $tallstackui.toast().success('Status geändert').send());
            $wire.on('nicht-abrechnen-gesetzt', () => $tallstackui.toast().success('Leistung wird bewusst nicht abgerechnet').send());
            $wire.on('nicht-abrechnen-entfernt', () => $tallstackui.toast().success('Kennzeichnung entfernt').send());
            $wire.on('leistung-archiviert', () => $tallstackui.toast().success('Leistung archiviert').send());
            $wire.on('leistung-reaktiviert', () => $tallstackui.toast().success('Archivierung aufgehoben').send());
            $wire.on('preisaenderung-gespeichert', () => $tallstackui.toast().success('Preisänderung gespeichert').send());
            $wire.on('preisaenderung-geloescht', () => $tallstackui.toast().success('Geplante Preisänderung gelöscht').send());
        </script>
        @endscript
    </x-page>
</div>
