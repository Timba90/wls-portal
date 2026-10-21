<div>
    <x-page :title="$this->isEditing() ? 'Subscription bearbeiten' : 'Subscription anlegen'" subtitle="Plesk-Webspace von Hand pflegen." back-label="Subscriptions" :back-url="$this->isEditing() ? route('subscriptions.show', $subscription) : route('subscriptions.index')">
        <form wire:submit="save" class="space-y-6">
            <x-errors title="Die Subscription konnte nicht gespeichert werden" />
            <x-card>
                <x-slot:header><h2 class="text-sm font-semibold text-ink">Webspace</h2></x-slot:header>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-input wire:model="name" label="Hauptdomain" placeholder="beispiel.de" required />
                    <x-select.styled wire:model="server" label="Server" placeholder="Server wählen" :options="$serverOptions" select="label:label|value:value" required />
                    <x-select.styled wire:model="status" label="Status" :options="$statusOptions" select="label:label|value:value" required />
                    <x-input wire:model="plesk_id" label="Plesk-ID" type="number" min="1" />
                    <x-input wire:model="subscriber" label="Plesk-Abonnent" hint="Die ursprüngliche Abonnentenangabe aus Plesk." />
                    <x-date wire:model="setup_date" label="In Plesk angelegt am" format="DD.MM.YYYY" />
                    <x-input wire:model="hosting_login" label="Hosting-Login" />
                    <x-input wire:model="service_plan" label="Plesk-Tarif" />
                    <x-input wire:model="ip_address" label="IP-Adresse" />
                    <x-date wire:model="expires_on" label="Läuft ab am" format="DD.MM.YYYY" />
                    <div class="md:col-span-2"><x-textarea wire:model="description" label="Beschreibung" rows="4" /></div>
                </div>
            </x-card>
            <x-card>
                <x-slot:header><h2 class="text-sm font-semibold text-ink">Kunde und Abrechnung</h2></x-slot:header>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-select.styled wire:model.live="customer_id" label="Kunde" placeholder="Ohne Kunde" searchable :options="$customerOptions" select="label:name|value:id" />
                    <x-select.styled wire:model="customer_service_id" label="Kundenleistung" placeholder="Ohne Leistung" searchable :options="$serviceOptions" select="label:name|value:id" hint="Die Abrechnung folgt den Einstellungen der verknüpften Kundenleistung." />
                </div>
            </x-card>
            <div class="flex justify-end gap-2">
                <x-button color="secondary" outline :href="$this->isEditing() ? route('subscriptions.show', $subscription) : route('subscriptions.index')" wire:navigate>Abbrechen</x-button>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $this->isEditing() ? 'Speichern' : 'Subscription anlegen' }}</x-button>
            </div>
        </form>
    </x-page>
</div>
