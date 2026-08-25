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

            {{--
                Die Kacheln zeigen den auf einen Monat normalisierten Umsatz.
                Hier steht daneben, wann er tatsächlich anfällt: eine
                Jahresleistung liegt in genau einem Monat, nicht in zwölf.
            --}}
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <x-panel :title="'Abrechnung je Monat'"
                         :subtitle="'Was in den nächsten zwölf Monaten fällig wird — insgesamt '.$forecast['total']->format().'.'">
                    <x-column-chart :months="$forecast['months']" :peak="$forecast['peak']" />

                    <div class="mt-4 grid gap-3 border-t border-line pt-3 sm:grid-cols-3">
                        <div>
                            <p class="text-[10.5px] uppercase tracking-wide text-ink-faint">Höchster Monat</p>
                            <p class="tabular text-[13px] text-ink-base">{{ $forecast['peak']->format() }}</p>
                        </div>

                        <div>
                            <p class="text-[10.5px] uppercase tracking-wide text-ink-faint">Schnitt je Monat</p>
                            <p class="tabular text-[13px] text-ink-base">{{ $forecast['average']->format() }}</p>
                        </div>

                        <div>
                            <p class="text-[10.5px] uppercase tracking-wide text-ink-faint">Zwölf Monate</p>
                            <p class="tabular text-[13px] text-ink-base">{{ $forecast['total']->format() }}</p>
                        </div>
                    </div>

                    @if ($forecast['unscheduled'] > 0)
                        {{--
                            Ohne Anfangsdatum lässt sich ein Rhythmus jenseits
                            des Monats nicht auf Monate legen. Lieber
                            ausweisen als raten.
                        --}}
                        <p class="mt-3 text-[11px] text-[color:var(--pill-warn-ink)]">
                            {{ trans_choice(
                                ':count Leistung ohne Abrechnungsdatum ist hier nicht enthalten.|:count Leistungen ohne Abrechnungsdatum sind hier nicht enthalten.',
                                $forecast['unscheduled'],
                                ['count' => $forecast['unscheduled']],
                            ) }}
                        </p>
                    @endif
                </x-panel>

                <x-panel title="Woraus sich das zusammensetzt"
                         subtitle="Anteil der Kategorien an denselben zwölf Monaten.">
                    <x-share-bars :shares="$forecast['composition']"
                                  empty="Keine abzurechnende Leistung erfasst." />
                </x-panel>
            </div>
        </div>
    </x-page>
</div>
