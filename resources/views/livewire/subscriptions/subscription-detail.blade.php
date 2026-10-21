<div>
    <x-page :title="$subscription->name" :subtitle="$subscription->server->label()" back-label="Subscriptions" :back-url="route('subscriptions.index')">
        <x-slot:actions><x-button color="secondary" outline icon="pencil-square" :href="route('subscriptions.edit', $subscription)" wire:navigate>Bearbeiten</x-button></x-slot:actions>
        <div class="grid items-start gap-3.5 lg:grid-cols-2">
            <x-panel title="Webspace" subtitle="Von Hand gepflegter Stand">
                <dl class="divide-y divide-line">
                    <x-detail-row label="Hauptdomain" :value="$subscription->name" />
                    <x-detail-row label="Server" :value="$subscription->server->label()" />
                    <x-detail-row label="Status"><x-status-pill :kind="$subscription->status->pillKind()" :label="$subscription->status->label()" /></x-detail-row>
                    <x-detail-row label="Plesk-ID" :value="$subscription->plesk_id" />
                    <x-detail-row label="Plesk-Abonnent" :value="$subscription->subscriber" />
                    <x-detail-row label="In Plesk angelegt am" :value="$subscription->setup_date?->format('d.m.Y')" />
                    <x-detail-row label="Hosting-Login" :value="$subscription->hosting_login" />
                    <x-detail-row label="Plesk-Tarif" :value="$subscription->service_plan" />
                    <x-detail-row label="IP-Adresse" :value="$subscription->ip_address" />
                    <x-detail-row label="Läuft ab" :value="$subscription->expires_on?->format('d.m.Y')" />
                    <x-detail-row label="Beschreibung"><span class="whitespace-pre-wrap">{{ $subscription->description ?: '—' }}</span></x-detail-row>
                </dl>
            </x-panel>
            <x-panel title="Kunde und Abrechnung">
                <dl class="divide-y divide-line">
                    <x-detail-row label="Kunde">
                        @if ($subscription->customer)
                            <a href="{{ route('customers.show', $subscription->customer) }}" wire:navigate class="text-accent hover:underline">{{ $subscription->customer->displayName() }}</a>
                        @else
                            <x-status-pill kind="warn" label="Ohne Kunde" />
                        @endif
                    </x-detail-row>
                    <x-detail-row label="Abrechnung"><x-inventory-billing :service="$subscription->customerService" /></x-detail-row>
                </dl>
            </x-panel>
            <div class="lg:col-span-2"><x-panel title="Verlauf" subtitle="Änderungen an dieser Subscription"><livewire:shared.audit-panel :auditable="$subscription" :key="'subscription-audit-'.$subscription->id" /></x-panel></div>
        </div>
    </x-page>
</div>
