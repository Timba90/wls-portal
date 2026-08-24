<div>
    <x-page :title="$product->name"
            :subtitle="$product->internal_name"
            back-label="Katalog ／ zurück zur Liste"
            :back-url="route('products.index')">
        <x-slot:actions>
            <x-button sm color="secondary" outline icon="pencil"
                      :href="route('products.edit', $product)" wire:navigate>
                Bearbeiten
            </x-button>
        </x-slot:actions>

        @if (session('erfolg'))
            <x-alert color="green" class="mb-3.5">{{ session('erfolg') }}</x-alert>
        @endif

        @if ($product->isArchived())
            <x-alert color="amber" class="mb-3.5" title="Archivierter Artikel">
                Bestehende Kundenleistungen bleiben unverändert bestehen.
            </x-alert>
        @endif

        {{-- Kopfkarte: Kürzel, Name mit Status, Kennzahlenreihe. --}}
        <div class="mb-3.5 flex flex-wrap items-center gap-4 rounded-[10px] border border-line bg-panel px-[17px] py-4">
            <x-avatar-initials :initials="Str::upper(Str::substr($product->name, 0, 2))" size="lg" />

            <div class="flex min-w-[210px] flex-[1_1_240px] flex-col gap-[5px]">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-[16px] font-semibold tracking-[-0.015em] text-ink">{{ $product->name }}</span>
                    <x-status-pill :kind="$product->isArchived() ? 'mute' : 'ok'" :label="$product->status->label()" />

                    @foreach ($product->tags as $tag)
                        <x-badge :color="$tag->color" :text="$tag->name" sm />
                    @endforeach
                </div>

                <span class="text-[11.5px] text-ink-muted">
                    {{ $product->internal_name }}
                    @if ($product->category)
                        · {{ $product->category->name }}@if ($product->subcategory) / {{ $product->subcategory->name }}@endif
                    @endif
                </span>
            </div>

            <div class="ml-auto flex flex-wrap gap-[22px]">
                @foreach ([
                    ['Verkaufspreis', $product->defaultSalesPrice()->format(), false],
                    ['Einkaufspreis', $product->defaultPurchasePrice()->format(), false],
                    ['Marge', $product->defaultMargin()->format(), $product->defaultMargin()->isNegative()],
                    ['Verträge', number_format($verwendung->count(), 0, ',', '.'), false],
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
                {{-- Verwendung in Verträgen --}}
                <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
                    <div class="flex items-baseline justify-between gap-3.5 border-b border-line px-[17px] py-[15px]">
                        <div class="flex flex-col gap-[3px]">
                            <h3 class="text-[13.5px] font-semibold tracking-[-0.01em] text-ink">Verwendung in Verträgen</h3>
                            <span class="text-[11.5px] text-ink-faint">
                                {{ trans_choice(
                                    'Bei :count Kunden im Einsatz|Bei :count Kunden im Einsatz',
                                    $verwendung->pluck('customer_id')->unique()->count(),
                                    ['count' => $verwendung->pluck('customer_id')->unique()->count()],
                                ) }}
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[600px]">
                            <div class="grid grid-cols-[1.7fr_0.9fr_0.9fr_1fr_0.9fr] gap-3 border-b border-line bg-raised px-[17px] py-2.5">
                                @foreach (['Kunde', 'Turnus', 'Preis', 'Beginn', 'Status'] as $spalte)
                                    <span @class([
                                        'truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint',
                                        'text-right' => $spalte === 'Preis',
                                    ])>{{ $spalte }}</span>
                                @endforeach
                            </div>

                            @forelse ($verwendung as $leistung)
                                <a href="{{ route('customer-services.show', [$leistung->customer, $leistung]) }}"
                                   wire:navigate
                                   wire:key="verwendung-{{ $leistung->id }}"
                                   class="grid grid-cols-[1.7fr_0.9fr_0.9fr_1fr_0.9fr] items-center gap-3 border-b border-line px-[17px] py-3 transition hover:bg-raised focus-visible:bg-raised focus-visible:outline-none">
                                    <span class="flex min-w-0 flex-col">
                                        <span class="truncate text-[12.5px] font-medium text-ink-base">
                                            {{ $leistung->customer->displayName() }}
                                        </span>
                                        <span class="truncate text-[10.5px] text-ink-faint">{{ $leistung->name }}</span>
                                    </span>

                                    <span class="truncate text-[12px] text-ink-muted">{{ $leistung->billingInterval()->label() }}</span>

                                    <span class="truncate tabular text-right text-[12.5px] text-ink">
                                        {{ $leistung->salesPrice()->format() }}
                                    </span>

                                    <span class="truncate text-[12px] text-ink-muted">
                                        {{ $leistung->service_start_date?->format('d.m.Y') ?? '—' }}
                                    </span>

                                    <span>
                                        <x-status-pill :kind="$leistung->status->pillKind()" :label="$leistung->status->label()" />
                                    </span>
                                </a>
                            @empty
                                <div class="px-[17px] py-[34px] text-center text-[12.5px] text-ink-faint">
                                    Dieser Artikel ist noch keinem Vertrag zugeordnet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Preisentwicklung des Listenpreises --}}
                <div class="rounded-[10px] border border-line bg-panel">
                    <div class="flex flex-col gap-[3px] border-b border-line px-[17px] py-[15px]">
                        <h3 class="text-[13.5px] font-semibold tracking-[-0.01em] text-ink">Preisentwicklung</h3>
                        <span class="text-[11.5px] text-ink-faint">
                            Listenpreis · aus der Änderungshistorie · wirkt nicht auf bestehende Kundenleistungen
                        </span>
                    </div>

                    <div class="px-[17px] pb-3.5 pt-1.5">
                        @forelse ($preisverlauf as $eintrag)
                            @php
                                $differenz = $eintrag['alt']?->cents !== null
                                    ? $eintrag['neu']->cents - $eintrag['alt']->cents
                                    : null;
                            @endphp

                            <div class="flex items-center gap-3.5 border-b border-line py-3" wire:key="preis-{{ $loop->index }}">
                                <span class="h-[7px] w-[7px] flex-none rounded-full"
                                      style="background: var(--pill-{{ $differenz === null ? 'info' : ($differenz > 0 ? 'ok' : 'bad') }}-ink)"></span>

                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="text-[12.5px] text-ink-base">{{ $eintrag['feld'] }}</span>
                                    <span class="text-[10.5px] text-ink-faint">
                                        {{ $eintrag['zeitpunkt']?->format('d.m.Y H:i') }}
                                        @if ($eintrag['benutzer'])
                                            · {{ $eintrag['benutzer'] }}
                                        @endif
                                    </span>
                                </span>

                                @if ($differenz !== null && $differenz !== 0)
                                    <span @class([
                                        'tabular text-[11.5px] font-medium',
                                        'text-[color:var(--pill-ok-ink)]' => $differenz > 0,
                                        'text-[color:var(--pill-bad-ink)]' => $differenz < 0,
                                    ])>
                                        {{ $differenz > 0 ? '+' : '' }}{{ \App\Support\Money::fromCents($differenz)->format() }}
                                    </span>
                                @endif

                                <span class="min-w-[78px] text-right tabular text-[12.5px] text-ink">
                                    {{ $eintrag['neu']->format() }}
                                </span>
                            </div>
                        @empty
                            <p class="py-[30px] text-center text-[12.5px] text-ink-faint">
                                Zu diesem Artikel liegen keine Preiseinträge in der Änderungshistorie.
                            </p>
                        @endforelse
                    </div>
                </div>

                {{-- Varianten: im Entwurf nicht vorgesehen, hier aber die einzige Stelle,
                     an der sie verwaltet werden. --}}
                <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm text-ink-muted">
                            Varianten übernehmen leere Preis- und Intervallangaben vom Artikel.
                        </p>

                        <x-button sm icon="plus" wire:click="createVariant">Variante anlegen</x-button>
                    </div>

                    <div class="divide-y divide-line">
                        @forelse ($product->variants as $variant)
                            <div class="py-3" wire:key="variant-{{ $variant->id }}">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-ink">{{ $variant->name }}</span>
                                            <x-badge :color="$variant->status->color()" :text="$variant->status->label()" sm />

                                            @if ($variant->overridesProductDefaults())
                                                <x-badge color="blue" text="Eigene Werte" sm />
                                            @endif
                                        </div>

                                        @if ($variant->description)
                                            <p class="mt-1 text-sm text-ink-muted">{{ $variant->description }}</p>
                                        @endif

                                        <p class="mt-1 text-sm text-ink-base">
                                            Einkauf {{ $variant->effectivePurchasePrice()->format() }}
                                            · Verkauf {{ $variant->effectiveSalesPrice()->format() }}
                                            · Marge {{ $variant->effectiveMargin()->format() }}
                                            · {{ $variant->effectiveBillingInterval()->label() }}
                                        </p>

                                        @if ($variant->serviceComponents->isNotEmpty())
                                            <ul class="mt-2 list-inside list-disc text-sm text-ink-muted">
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
                            <p class="py-6 text-center text-sm text-ink-muted">
                                Dieser Artikel besitzt keine Varianten.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Rechte Spalte: Stammdaten, Leistungsumfang, Aktionen. --}}
            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Stammdaten</span>

                    @foreach ($this->masterData() as $label => $wert)
                        <div class="flex items-baseline justify-between gap-3" wire:key="stamm-{{ $loop->index }}">
                            <span class="text-[11.5px] text-ink-muted">{{ $label }}</span>
                            <span class="truncate text-right text-[12px] text-ink-base">{{ blank($wert) ? '—' : $wert }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Leistungsumfang</span>

                    @forelse ($product->serviceComponents as $bestandteil)
                        <div class="flex items-start gap-2.5" wire:key="bestandteil-{{ $bestandteil->id }}">
                            <span class="mt-[6px] h-[5px] w-[5px] flex-none rounded-full bg-accent"></span>
                            <span class="text-[12px] leading-normal text-ink-base">{{ $bestandteil->title }}</span>
                        </div>
                    @empty
                        <p class="text-[11.5px] text-ink-faint">Keine Bestandteile hinterlegt.</p>
                    @endforelse
                </div>

                @if ($product->description)
                    <div class="flex flex-col gap-2 rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                        <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Beschreibung</span>
                        <p class="text-[12px] leading-relaxed text-ink-base">{{ $product->description }}</p>
                    </div>
                @endif

                <div class="flex flex-col gap-2.5 rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Aktionen</span>

                    <x-button sm block :href="route('products.edit', $product)" wire:navigate>Preis anpassen</x-button>

                    @if ($product->isArchived())
                        <span class="text-[11px] leading-normal text-ink-faint">
                            Der Artikel ist archiviert und steht beim Anlegen neuer Leistungen nicht zur Auswahl.
                        </span>

                        <x-button sm block color="secondary" outline wire:click="restore">
                            Archivierung aufheben
                        </x-button>
                    @else
                        <span class="text-[11px] leading-normal text-ink-faint">
                            Beim Archivieren verschwindet der Artikel aus der Auswahl. Bestehende
                            Kundenleistungen bleiben unverändert.
                        </span>

                        <x-button sm
                                  block
                                  color="red"
                                  outline
                                  x-on:click="$dialog.confirm({
                                      title: 'Artikel archivieren?',
                                      description: 'Der Artikel und seine Varianten werden archiviert. Bestehende Kundenleistungen bleiben unverändert.',
                                      accept: { text: 'Archivieren', method: 'archive' },
                                      reject: { text: 'Abbrechen' },
                                  })">
                            Artikel deaktivieren
                        </x-button>
                    @endif
                </div>

                <div class="rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="mb-3 block text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">
                        Eigene Felder
                    </span>

                    <livewire:custom-fields.custom-fields-panel :record="$product"
                                                                :read-only="$product->isArchived()"
                                                                :key="'felder-artikel-'.$product->id" />
                </div>
            </div>
        </div>

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
                    <p class="mb-2 text-sm font-semibold text-ink">Leistungsbestandteile</p>

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
    </x-page>
</div>
