<div>
    <x-page title="Übersicht"
            subtitle="Kunden, Leistungen und wiederkehrende Kennzahlen auf einen Blick.">
        <x-slot:actions>
            <x-button sm :href="route('customers.create')" wire:navigate icon="plus">Kunde anlegen</x-button>
        </x-slot:actions>

        <div class="flex flex-col gap-4">
            {{-- Kennzahlreihe --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-kpi-tile label="Aktive Kunden"
                            :value="number_format($metrics['activeCustomers'], 0, ',', '.')"
                            :note="number_format($metrics['archivedCustomers'], 0, ',', '.').' archiviert'"
                            :href="route('customers.index')" />

                <x-kpi-tile label="Aktive Leistungen"
                            :value="number_format($metrics['activeServices'], 0, ',', '.')"
                            :note="number_format($metrics['billableServices'], 0, ',', '.').' abrechnungsrelevant'"
                            :href="route('services.index')" />

                <x-kpi-tile label="Umsatz / Monat"
                            :value="$metrics['monthlyRevenue']->format()"
                            :note="$metrics['yearlyRevenue']->format().' im Jahr'" />

                <x-kpi-tile label="Kosten / Monat"
                            :value="$metrics['monthlyCosts']->format()"
                            :note="$metrics['yearlyCosts']->format().' im Jahr'" />

                <x-kpi-tile label="Marge / Monat"
                            :value="$metrics['monthlyMargin']->format()"
                            :note="$metrics['marginPercentage'] !== null
                                ? number_format($metrics['marginPercentage'], 1, ',', '.').' % vom Umsatz'
                                : 'kein Umsatz erfasst'" />
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
                <x-panel title="Bestand"
                         subtitle="Stammdaten der Anwendung.">
                    <dl class="divide-y divide-line text-[12.5px]">
                        <x-detail-row label="Aktive Kunden" :value="number_format($metrics['activeCustomers'], 0, ',', '.')" />
                        <x-detail-row label="Archivierte Kunden" :value="number_format($metrics['archivedCustomers'], 0, ',', '.')" />
                        <x-detail-row label="Ansprechpartner" :value="number_format($metrics['activeContacts'], 0, ',', '.')" />
                        <x-detail-row label="Artikel / Leistungen" :value="number_format($metrics['products'], 0, ',', '.')" />
                        <x-detail-row label="Aktive Kundenleistungen" :value="number_format($metrics['activeServices'], 0, ',', '.')" />
                    </dl>
                </x-panel>

                <x-panel title="Nicht in den Kennzahlen"
                         subtitle="Nur aktive, wiederkehrende Leistungen ohne die Kennzeichnung „Bewusst nicht abrechnen“ zählen.">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[12px] text-ink-muted">Bewusst nicht abrechnen</span>
                            <x-status-pill kind="warn" :label="$metrics['doNotBillServices'].' Leistungen'" :dot="false" />
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[12px] text-ink-muted">Einmalige Leistungen</span>
                            <x-status-pill kind="mute" :label="$metrics['oneTimeServices'].' Leistungen'" :dot="false" />
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-line pt-3">
                            <span class="text-[12px] text-ink-muted">In die Kennzahlen einbezogen</span>
                            <x-status-pill kind="ok" :label="$metrics['billableServices'].' Leistungen'" :dot="false" />
                        </div>
                    </div>
                </x-panel>
            </div>
        </div>
    </x-page>
</div>
