<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Überblick über Kunden, Leistungen und Kennzahlen.
        </p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-tile label="Aktive Kunden"
                       :value="number_format($metrics['activeCustomers'], 0, ',', '.')"
                       :href="route('customers.index')" />

        <x-metric-tile label="Archivierte Kunden"
                       :value="number_format($metrics['archivedCustomers'], 0, ',', '.')"
                       :href="route('archive.index')" />

        <x-metric-tile label="Artikel / Leistungen"
                       :value="number_format($metrics['products'], 0, ',', '.')"
                       :href="route('products.index')" />

        <x-metric-tile label="Aktive Kundenleistungen"
                       :value="number_format($metrics['activeServices'], 0, ',', '.')"
                       :href="route('services.index')" />
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Soll-Umsatz</h2>
            </x-slot:header>

            <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
                <x-detail-row label="Monatlich" :value="$metrics['monthlyRevenue']->format()" />
                <x-detail-row label="Jährlich" :value="$metrics['yearlyRevenue']->format()" />
            </dl>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Kosten</h2>
            </x-slot:header>

            <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
                <x-detail-row label="Monatlich" :value="$metrics['monthlyCosts']->format()" />
                <x-detail-row label="Jährlich" :value="$metrics['yearlyCosts']->format()" />
            </dl>
        </x-card>
    </div>

    <x-card class="mb-6">
        <x-slot:header>
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Marge</h2>
        </x-slot:header>

        <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
            <x-detail-row label="Monatlich" :value="$metrics['monthlyMargin']->format()" />
            <x-detail-row label="Jährlich" :value="$metrics['yearlyMargin']->format()" />
            <x-detail-row label="Marge in Prozent"
                          :value="$metrics['marginPercentage'] !== null
                              ? number_format($metrics['marginPercentage'], 1, ',', '.').' %'
                              : null" />
        </dl>
    </x-card>

    <x-card>
        <x-slot:header>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Nicht in den Kennzahlen enthalten</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    In Umsatz, Kosten und Marge fließen ausschließlich aktive, wiederkehrende Leistungen
                    ohne die Kennzeichnung „Bewusst nicht abrechnen" ein.
                </p>
            </div>
        </x-slot:header>

        <dl class="divide-y divide-gray-200 text-sm dark:divide-dark-600">
            <x-detail-row label="In die Kennzahlen einbezogen"
                          :value="number_format($metrics['billableServices'], 0, ',', '.').' Leistungen'" />
            <x-detail-row label="Bewusst nicht abrechnen"
                          :value="number_format($metrics['doNotBillServices'], 0, ',', '.').' Leistungen'" />
            <x-detail-row label="Einmalige Leistungen"
                          :value="number_format($metrics['oneTimeServices'], 0, ',', '.').' Leistungen'" />
        </dl>
    </x-card>
</div>
