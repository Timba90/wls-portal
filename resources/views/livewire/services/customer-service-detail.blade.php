@php
    $abgleich = $service->hasCatalogOrigin() ? $this->catalogComparison() : [];
    $offeneKatalogaenderungen = $service->hasCatalogOrigin() ? $this->openCatalogChangeCount() : 0;

    $reiter = collect([
        'preise' => 'Preisverlauf',
        'bestandteile' => 'Bestandteile',
    ])
        // Der Reiter erscheint nur, wenn es einen Katalogartikel gibt, gegen
        // den sich vergleichen lässt.
        ->when($service->hasCatalogOrigin(), fn ($liste) => $liste->put('katalog', 'Katalog'))
        ->merge([
            'notizen' => 'Notizen',
            'dokumente' => 'Dokumente',
            'felder' => 'Eigene Felder',
            'verlauf' => 'Verlauf',
        ]);
@endphp

<div>
    <x-page :title="$service->name"
            :subtitle="$customer->customer_number.' · '.$customer->displayName()"
            back-label="Leistungen ／ zurück zum Kunden"
            :back-url="route('customers.show', ['customer' => $customer, 'bereich' => 'leistungen'])">
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

                @endif
        </x-slot:actions>


        {{-- Kopfkarte: Kürzel, Name mit Status, Kunde und Kennzahlenreihe. --}}
        <div class="mb-3.5 flex flex-wrap items-center gap-4 rounded-[10px] border border-line bg-panel px-[17px] py-4">
            <x-avatar-initials :initials="Str::upper(Str::substr($service->name, 0, 2))" size="lg" />

            <div class="flex min-w-[210px] flex-[1_1_240px] flex-col gap-[5px]">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-[16px] font-semibold tracking-[-0.015em] text-ink">{{ $service->name }}</span>
                    <x-status-pill :kind="$service->status->pillKind()" :label="$service->status->label()" />

                    @if ($service->do_not_bill)
                        <x-status-pill kind="warn"
                                       :label="'Nicht abrechnen · '.$service->do_not_bill_reason?->label()"
                                       :dot="false" />
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-[7px]">
                    <a href="{{ route('customers.show', $customer) }}"
                       wire:navigate
                       class="text-[11.5px] font-medium text-accent hover:underline">
                        {{ $customer->displayName() }}
                    </a>

                    <span class="text-[11.5px] text-ink-muted">
                        {{ $service->billingInterval()->label() }}
                        @if ($service->product)
                            · {{ $service->product->name }}
                        @else
                            · Individuelle Leistung
                        @endif
                    </span>
                </div>
            </div>

            <div class="ml-auto flex flex-wrap gap-[22px]">
                @foreach ([
                    ['Verkaufspreis', $service->salesPrice()->format(), false],
                    ['Einkaufspreis', $service->purchasePrice()->format(), false],
                    ['Marge', $service->margin()->format(), $service->margin()->isNegative()],
                    ['Umsatz / Mon', $service->monthlyRevenue()->format(), false],
                ] as [$label, $wert, $negativ])
                    <div class="flex flex-col gap-1" wire:key="kennzahl-{{ $loop->index }}">
                        <span class="text-[9.5px] font-semibold uppercase tracking-[0.09em] text-ink-faint">{{ $label }}</span>
                        <span @class([
                            'tabular text-[15px] font-semibold',
                            'text-[color:var(--pill-bad-ink)]' => $negativ,
                            'text-ink' => ! $negativ,
                        ])>{{ $wert }}</span>
                    </div>
                @endforeach
            </div>
        </div>

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
                                ])>
                            {{ $beschriftung }}

                            @if ($schluessel === 'katalog' && $offeneKatalogaenderungen > 0)
                                <span @class([
                                    'ml-1 rounded-full px-1.5 py-px font-mono text-[10px] tabular-nums',
                                    'bg-accent-ink/15' => $tab === $schluessel,
                                    'bg-[color:var(--pill-warn-bg)] text-[color:var(--pill-warn-ink)]' => $tab !== $schluessel,
                                ])>{{ $offeneKatalogaenderungen }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @switch($tab)
                    @case('bestandteile')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <x-service-components-list :components="$service->serviceComponents" />
                        </div>
                        @break

                    @case('katalog')
                        <div class="rounded-[10px] border border-line bg-panel">
                            <div class="flex flex-wrap items-baseline justify-between gap-3.5 border-b border-line px-[17px] py-[15px]">
                                <div class="flex flex-col gap-[3px]">
                                    <h3 class="text-[13.5px] font-semibold tracking-[-0.01em] text-ink">Katalogabgleich</h3>
                                    <span class="text-[11.5px] text-ink-faint">
                                        {{ $service->product?->name }} · zuletzt gesehen
                                        {{ optional($service->catalog_reviewed_at ?? $service->created_at)->format('d.m.Y') }}
                                    </span>
                                </div>

                                @if ($offeneKatalogaenderungen > 0 && ! $service->isArchived())
                                    <x-button sm
                                              color="secondary"
                                              outline
                                              wire:click="adoptAllCatalogChanges"
                                              wire:confirm="Alle geänderten Katalogwerte übernehmen? Preise werden dabei im Preisverlauf festgehalten.">
                                        Alle übernehmen
                                    </x-button>
                                @endif
                            </div>

                            @forelse ($abgleich as $zeile)
                                <div wire:key="abgleich-{{ $zeile['feld'] }}"
                                     class="flex flex-col gap-3 border-b border-line px-[17px] py-3.5 last:border-b-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[12.5px] font-medium text-ink">{{ $zeile['label'] }}</span>

                                        @if ($zeile['katalogGeaendert'])
                                            <x-status-pill kind="warn" label="Katalog geändert" />
                                        @endif

                                        @if ($zeile['kundeWeichtAb'])
                                            <x-status-pill kind="info" label="Kunde weicht ab" />
                                        @endif
                                    </div>

                                    {{--
                                        Drei Stände nebeneinander. Ohne den mittleren ließe sich nicht
                                        unterscheiden, ob der Kunde bewusst abweicht oder ob sich der
                                        Katalog seither geändert hat.
                                    --}}
                                    <div class="grid gap-2.5 sm:grid-cols-3">
                                        @foreach ([
                                            ['Zuletzt gesehen', $zeile['stand'], false],
                                            ['Katalog heute', $zeile['katalog'], $zeile['katalogGeaendert']],
                                            ['Diese Leistung', $zeile['leistung'], false],
                                        ] as [$titel, $wert, $hervorheben])
                                            <div @class([
                                                'flex flex-col gap-1 rounded-[8px] border px-2.5 py-2',
                                                'border-[color:var(--pill-warn-ink)]/40 bg-[color:var(--pill-warn-bg)]' => $hervorheben,
                                                'border-line bg-raised' => ! $hervorheben,
                                            ])>
                                                <span class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">{{ $titel }}</span>
                                                <span @class([
                                                    'truncate text-[12.5px]',
                                                    'font-medium text-[color:var(--pill-warn-ink)]' => $hervorheben,
                                                    'text-ink-base' => ! $hervorheben,
                                                ])>{{ $wert }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if ($zeile['uebernehmbar'])
                                        <div class="flex flex-wrap gap-2">
                                            <x-button sm
                                                      wire:click="resolveCatalogChange('{{ $zeile['feld'] }}', true)"
                                                      wire:confirm="„{{ $zeile['katalog'] }}&#34; aus dem Katalog übernehmen?">
                                                Katalogwert übernehmen
                                            </x-button>

                                            <x-button sm
                                                      color="secondary"
                                                      outline
                                                      wire:click="resolveCatalogChange('{{ $zeile['feld'] }}', false)">
                                                Kundenwert behalten
                                            </x-button>
                                        </div>
                                    @elseif ($zeile['katalogGeaendert'] && $zeile['feld'] === 'product_name')
                                        <span class="text-[11.5px] text-ink-faint">
                                            Die Leistung trägt einen eigenen Namen — er wird nicht automatisch mitgeändert.
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                                    Diese Leistung entspricht dem Katalog.
                                </div>
                            @endforelse
                        </div>
                        @break

                    @case('notizen')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.notes-panel :notable="$service" :key="'notizen-leistung-'.$service->id" />
                        </div>
                        @break

                    @case('dokumente')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.documents-panel :documentable="$service" :key="'dokumente-leistung-'.$service->id" />
                        </div>
                        @break

                    @case('felder')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:custom-fields.custom-fields-panel :record="$service"
                                                                        :read-only="$service->isArchived()"
                                                                        :key="'felder-leistung-'.$service->id" />
                        </div>
                        @break

                    @case('verlauf')
                        <div class="rounded-[10px] border border-line bg-panel">
                            <div class="flex flex-col gap-[3px] border-b border-line px-[17px] py-[15px]">
                                <h3 class="text-[13.5px] font-semibold tracking-[-0.01em] text-ink">Verlauf</h3>
                                <span class="text-[11.5px] text-ink-faint">Änderungen an dieser Vertragsposition</span>
                            </div>

                            <div class="p-[17px]">
                                <livewire:shared.audit-panel :auditable="$service" :key="'historie-leistung-'.$service->id" />
                            </div>
                        </div>
                        @break

                    @default
                        <div class="rounded-[10px] border border-line bg-panel">
                            <div class="flex items-baseline justify-between gap-3.5 border-b border-line px-[17px] py-[15px]">
                                <div class="flex flex-col gap-[3px]">
                                    <h3 class="text-[13.5px] font-semibold tracking-[-0.01em] text-ink">Preisverlauf</h3>
                                    <span class="text-[11.5px] text-ink-faint">
                                        Preise werden nie überschrieben · rückwirkende Änderungen sind ausgeschlossen
                                    </span>
                                </div>

                                @unless ($service->isArchived())
                                    <button type="button"
                                            wire:click="openPriceChangeForm('sales')"
                                            class="cursor-pointer text-[11.5px] text-accent hover:underline">
                                        Preisänderung planen
                                    </button>
                                @endunless
                            </div>

                            <div class="p-[17px]">
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
                            </div>
                        </div>
                @endswitch
            </div>

            {{-- Rechte Spalte: Vertragsdaten, Basisartikel, Aktionen. --}}
            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Vertragsdaten</span>

                    @foreach ($this->contractData() as $label => $wert)
                        <div class="flex items-baseline justify-between gap-3" wire:key="vertrag-{{ $loop->index }}">
                            <span class="text-[11.5px] text-ink-muted">{{ $label }}</span>
                            <span class="truncate text-right text-[12px] text-ink-base">{{ blank($wert) ? '—' : $wert }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($service->product)
                    <div class="flex flex-col gap-2.5 rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Basisartikel</span>
                            <a href="{{ route('products.show', $service->product) }}"
                               wire:navigate
                               class="text-[11px] text-accent hover:underline">öffnen</a>
                        </div>

                        <div class="flex items-center gap-[11px]">
                            <x-avatar-initials :initials="Str::upper(Str::substr($service->product->name, 0, 2))" size="sm" />

                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="truncate text-[12.5px] text-ink-base">{{ $service->product->name }}</span>
                                <span class="truncate font-mono text-[10.5px] text-ink-faint">
                                    {{ $service->product->internal_name }}
                                </span>
                            </span>
                        </div>

                        <div class="flex items-baseline justify-between gap-3 pt-0.5">
                            <span class="text-[11.5px] text-ink-muted">Listenpreis</span>
                            <span class="tabular text-[12px] text-ink-base">
                                {{ $service->product->defaultSalesPrice()->format() }}
                            </span>
                        </div>

                        @php $abweichung = $this->priceDeviation(); @endphp

                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[11.5px] text-ink-muted">Abweichung</span>
                            <span @class([
                                'tabular text-[12px] font-medium',
                                'text-ink-faint' => $abweichung === 0,
                                'text-[color:var(--pill-ok-ink)]' => $abweichung > 0,
                                'text-[color:var(--pill-bad-ink)]' => $abweichung < 0,
                            ])>
                                @if ($abweichung === 0)
                                    keine
                                @else
                                    {{ $abweichung > 0 ? '+' : '' }}{{ \App\Support\Money::fromCents($abweichung)->format() }}
                                @endif
                            </span>
                        </div>
                    </div>
                @endif

                @unless ($service->isArchived())
                    <div class="flex flex-col gap-2.5 rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                        <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Aktionen</span>

                        <x-button sm block wire:click="openPriceChangeForm('sales')">Preis anpassen</x-button>

                        <div class="flex flex-col gap-1.5">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink-faint">Status</span>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($statusOptions as $option)
                                    <button type="button"
                                            wire:click="changeStatus('{{ $option['value'] }}')"
                                            wire:key="status-{{ $option['value'] }}"
                                            @class([
                                                'rounded-[7px] border px-2.5 py-1.5 text-[11.5px] font-medium transition',
                                                'border-accent bg-accent text-accent-ink' => $service->status->value === $option['value'],
                                                'border-line bg-raised text-ink-muted hover:border-line-strong hover:text-ink-base'
                                                    => $service->status->value !== $option['value'],
                                            ])>{{ $option['label'] }}</button>
                                @endforeach
                            </div>
                        </div>

                        <span class="text-[11px] leading-normal text-ink-faint">
                            Archivierte Leistungen sind vollständig schreibgeschützt und bleiben historisch erhalten.
                        </span>

                        <x-button sm
                                  block
                                  color="red"
                                  outline
                                  x-on:click="$dialog.confirm({
                                      title: 'Leistung archivieren?',
                                      description: 'Archivierte Leistungen sind vollständig schreibgeschützt und bleiben historisch erhalten.',
                                      accept: { text: 'Archivieren', method: 'archive' },
                                      reject: { text: 'Abbrechen' },
                                  })">
                            Leistung archivieren
                        </x-button>
                    </div>
                @endunless
            </div>
        </div>

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
