@php
    $reiter = [
        'notizen' => 'Notizen',
        'dokumente' => 'Dokumente',
        'felder' => 'Eigene Felder',
        'verlauf' => 'Verlauf',
    ];

    $tage = $domain->daysUntilExpiry();
@endphp

<div>
    <x-page :title="$domain->name"
            :subtitle="$domain->provider->label().' · '.($domain->customer?->displayName() ?? 'ohne Kunde')"
            back-label="Domains"
            :back-url="route('domains.index')">
        <x-slot:actions>
            <x-button sm
                      color="secondary"
                      outline
                      icon="user-plus"
                      wire:click="editAssignment">
                {{ $domain->customer ? 'Zuordnung ändern' : 'Kunde zuordnen' }}
            </x-button>
        </x-slot:actions>

        <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex gap-1 overflow-x-auto rounded-[9px] border border-line bg-panel p-1">
                    @foreach ($reiter as $schluessel => $beschriftung)
                        <button type="button"
                                wire:click="$set('tab', '{{ $schluessel }}')"
                                @class([
                                    'flex-1 whitespace-nowrap rounded-[6px] px-2.5 py-1.5 text-[12px] font-medium transition',
                                    'bg-raised text-ink' => $tab === $schluessel,
                                    'text-ink-muted hover:text-ink-base' => $tab !== $schluessel,
                                ])>
                            {{ $beschriftung }}
                        </button>
                    @endforeach
                </div>

                @switch($tab)
                    @case('dokumente')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.documents-panel :documentable="$domain" :key="'dokumente-domain-'.$domain->id" />
                        </div>
                        @break

                    @case('felder')
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:custom-fields.custom-fields-panel :record="$domain"
                                                                        :key="'felder-domain-'.$domain->id" />
                        </div>
                        @break

                    @case('verlauf')
                        <div class="rounded-[10px] border border-line bg-panel">
                            <div class="flex flex-col gap-[3px] border-b border-line px-[17px] py-[15px]">
                                <h3 class="text-[13.5px] font-semibold tracking-[-0.01em] text-ink">Verlauf</h3>
                                <span class="text-[11.5px] text-ink-faint">
                                    Änderungen an dieser Domain — auch die des nächtlichen Abgleichs
                                </span>
                            </div>

                            <div class="p-[17px]">
                                <livewire:shared.audit-panel :auditable="$domain" :key="'verlauf-domain-'.$domain->id" />
                            </div>
                        </div>
                        @break

                    @default
                        <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                            <livewire:shared.notes-panel :notable="$domain" :key="'notizen-domain-'.$domain->id" />
                        </div>
                @endswitch
            </div>

            <div class="flex flex-col gap-3.5">
                {{--
                    Der technische Stand kommt aus der Schnittstelle des
                    Registrars und ist deshalb nur zu lesen: geändert wird er
                    beim Anbieter, nicht hier.
                --}}
                <x-panel title="Technischer Stand"
                         :subtitle="'Abgeglichen: '.($domain->synced_at?->format('d.m.Y H:i') ?? 'noch nie')">
                    <dl class="divide-y divide-line">
                        <x-detail-row label="Läuft ab">
                            <div class="flex flex-col">
                                <span class="tabular">{{ $domain->expires_on?->format('d.m.Y') ?? '—' }}</span>

                                @if ($tage !== null)
                                    <span @class([
                                        'text-[10.5px]',
                                        'text-[color:var(--pill-bad-ink)]' => $tage < 0,
                                        'text-[color:var(--pill-warn-ink)]' => $tage >= 0 && $tage <= 60,
                                        'text-ink-faint' => $tage > 60,
                                    ])>
                                        @if ($tage < 0)
                                            seit {{ abs($tage) }} Tagen abgelaufen
                                        @elseif ($tage === 0)
                                            läuft heute ab
                                        @else
                                            noch {{ $tage }} Tage
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </x-detail-row>

                        <x-detail-row label="Registriert" :value="$domain->registered_on?->format('d.m.Y')" />

                        <x-detail-row label="Verlängerung">
                            <x-status-pill :kind="$domain->auto_renew ? 'ok' : 'mute'"
                                           :label="$domain->auto_renew ? 'Automatisch' : 'Manuell'" />
                        </x-detail-row>

                        <x-detail-row label="Status">
                            <span class="font-mono text-[11.5px] text-ink-muted">{{ $domain->status }}</span>
                        </x-detail-row>

                        <x-detail-row label="Anbieter" :value="$domain->provider->label()" />

                        <x-detail-row label="Nameserver">
                            @forelse ($domain->nameservers ?? [] as $nameserver)
                                <div class="truncate font-mono text-[11.5px] text-ink-muted">{{ $nameserver }}</div>
                            @empty
                                —
                            @endforelse
                        </x-detail-row>
                    </dl>
                </x-panel>

                <x-panel title="Zuordnung" subtitle="Wem die Domain gehört, weiß nur das Portal.">
                    <dl class="divide-y divide-line">
                        <x-detail-row label="Kunde">
                            @if ($domain->customer)
                                <a href="{{ route('customers.show', $domain->customer) }}"
                                   wire:navigate
                                   class="text-accent hover:underline">{{ $domain->customer->displayName() }}</a>
                            @else
                                <x-status-pill kind="warn" label="Ohne Kunde" />
                            @endif
                        </x-detail-row>

                        <x-detail-row label="Leistung">
                            @if ($domain->customerService)
                                <a href="{{ route('customer-services.show', [$domain->customerService->customer_id, $domain->customerService]) }}"
                                   wire:navigate
                                   class="text-accent hover:underline">
                                    {{ $domain->customerService->billing_label ?: $domain->customerService->name }}
                                </a>
                            @else
                                <span class="text-ink-faint">nicht einzeln abgerechnet</span>
                            @endif
                        </x-detail-row>
                    </dl>
                </x-panel>
            </div>
        </div>

        <x-modal wire="showAssignmentForm" id="zuordnung-formular" title="Zuordnung" persistent>
            <x-errors title="Die Zuordnung konnte nicht gespeichert werden" class="mb-4" />

            <div class="space-y-4">
                <x-select.styled wire:model.live="assignmentCustomerId"
                                 label="Kunde"
                                 placeholder="Ohne Kunde"
                                 searchable
                                 :options="$this->assignableCustomers()"
                                 select="label:name|value:id" />

                <x-select.styled wire:model="assignmentServiceId"
                                 label="Kundenleistung"
                                 placeholder="Ohne Leistung"
                                 :options="$this->assignableServices()"
                                 select="label:name|value:id"
                                 hint="Die Verbindung zur Abrechnung. Freiwillig — manches läuft im Paket mit." />
            </div>

            <x-slot:footer>
                <div class="flex w-full items-center justify-between gap-2">
                    <x-button color="secondary"
                              outline
                              wire:click="createServiceAndAssign"
                              wire:loading.attr="disabled"
                              wire:target="createServiceAndAssign"
                              title="Legt eine Domain-Leistung zum Katalogpreis an und verknüpft sie sofort">
                        {{ $creatingService ? 'Leistung wird angelegt …' : 'Leistung anlegen & zuordnen' }}
                    </x-button>

                    <div class="flex justify-end gap-2">
                        <x-button color="secondary" outline wire:click="closeAssignment">Abbrechen</x-button>
                        <x-button wire:click="saveAssignment">Speichern</x-button>
                    </div>
                </div>
            </x-slot:footer>
        </x-modal>
    </x-page>

    @script
    <script>
        $wire.on('zuordnung-gespeichert', () => $tsui.interaction('toast').success('Zuordnung gespeichert').send());
    </script>
    @endscript
</div>
