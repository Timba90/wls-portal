@php
    $columns = $this->tableHeaders();
    $grid = '36px '.collect($columns)->map(fn (array $column): string => $column['width'] ?? match ($column['index']) { 'name' => '1.7fr', 'billing', 'customer' => '1.4fr', default => '1fr' })->implode(' ');
@endphp
<div>
    <x-page title="Subscriptions" subtitle="Plesk-Webspaces mit Server, Kunde und Abrechnung. Der Bestand wird von Hand gepflegt.">
        <x-slot:actions>
            <x-button icon="plus" :href="route('subscriptions.create')" wire:navigate>Subscription anlegen</x-button>
        </x-slot:actions>
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="lg:w-72"><x-input wire:model.live.debounce.300ms="search" label="Suche" placeholder="Hauptdomain" icon="magnifying-glass" /></div>
            <div class="lg:w-44"><x-select.styled wire:model.live="server" label="Server" placeholder="Alle" :options="$serverOptions" select="label:label|value:value" /></div>
            <div class="lg:w-44"><x-select.styled wire:model.live="status" label="Status" placeholder="Alle" :options="$statusOptions" select="label:label|value:value" /></div>
            <div class="lg:w-56"><x-select.styled wire:model.live="customerFilter" label="Kunde" placeholder="Alle" searchable :options="$customerOptions" select="label:name|value:id" /></div>
            <div class="flex gap-2 lg:ml-auto">
                <x-button color="secondary" outline sm wire:click="resetFilters">Filter zurücksetzen</x-button>
                <x-button color="secondary" outline sm icon="adjustments-horizontal" wire:click="$set('showTableSettings', true)" title="Spalten einrichten" />
            </div>
        </div>
        <x-errors />
        @if (session('subscriptions-deleted'))
            <p role="status" class="mb-3 text-sm text-accent">{{ session('subscriptions-deleted') }}</p>
        @endif
        <div class="mb-3 flex items-center gap-3">
            <span class="text-sm text-ink-muted">{{ count($selectedIds) }} ausgewählt · Auswahl gilt für diese Seite</span>
            <x-button color="red" sm wire:click="confirmDeletion" :disabled="count($selectedIds) === 0" wire:loading.attr="disabled">Ausgewählte löschen</x-button>
        </div>
        <p class="mb-3 text-right text-[11.5px] text-ink-faint">{{ $subscriptions->total() }} Subscriptions in der Auswahl</p>
        <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
            <div class="overflow-x-auto">
                <div role="table" aria-label="Subscriptions" style="min-width: {{ max(850, count($columns) * 150) }}px">
                    <div role="row" class="grid gap-3.5 border-b border-line bg-raised px-[17px] py-2.5" style="grid-template-columns: {{ $grid }}">
                        <span role="columnheader"><x-checkbox aria-label="Alle auf dieser Seite auswählen" wire:click="togglePageSelection" :checked="$subscriptions->count() > 0 && count(array_diff($subscriptions->pluck('id')->map(fn ($id) => (string) $id)->all(), $selectedIds)) === 0" /></span>
                        @foreach ($columns as $column)
                            <span wire:key="heading-{{ $column['index'] }}" role="columnheader" class="text-[11px] font-semibold text-ink-faint">{{ $column['label'] }}</span>
                        @endforeach
                    </div>
                    @forelse ($subscriptions as $subscription)
                        <div wire:key="subscription-{{ $subscription->id }}" role="row" class="grid items-center gap-3.5 border-b border-line px-[17px] py-3 hover:bg-raised" style="grid-template-columns: {{ $grid }}">
                            <span role="cell"><x-checkbox wire:model.live="selectedIds" :value="(string) $subscription->id" :aria-label="'Auswählen: '.$subscription->name.' ('.$subscription->server->label().')'" /></span>
                            @foreach ($columns as $column)
                                <div wire:key="cell-{{ $subscription->id }}-{{ $column['index'] }}" role="cell" class="min-w-0 text-[12px] text-ink-muted">
                                    @switch($column['index'])
                                        @case('name')
                                            <a href="{{ route('subscriptions.show', $subscription) }}" wire:navigate class="block truncate text-[12.5px] text-ink-base hover:text-accent hover:underline">{{ $subscription->name }}</a>
                                            @break
                                        @case('server') {{ $subscription->server->label() }} @break
                                        @case('status') <x-status-pill :kind="$subscription->status->pillKind()" :label="$subscription->status->label()" /> @break
                                        @case('customer')
                                            @if ($subscription->customer)
                                                <a href="{{ route('customers.show', $subscription->customer) }}" wire:navigate class="block truncate hover:text-accent">{{ $subscription->customer->displayName() }}</a>
                                            @else
                                                <x-status-pill kind="warn" label="Ohne Kunde" />
                                            @endif
                                            @break
                                        @case('billing') <x-inventory-billing :service="$subscription->customerService" /> @break
                                        @case('setup_date') {{ $subscription->setup_date?->format('d.m.Y') ?? '—' }} @break
                                        @case('expires_on') {{ $subscription->expires_on?->format('d.m.Y') ?? '—' }} @break
                                        @default <span class="block truncate">{{ $subscription->{$column['index']} ?? '—' }}</span>
                                    @endswitch
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-[17px] py-10 text-center">
                            <p class="text-sm text-ink-base">Keine Subscriptions gefunden.</p>
                            <p class="mt-2 text-[12px] text-ink-faint">Legen Sie einen Webspace an oder passen Sie die Filter an.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @if ($subscriptions->hasPages())
                <div class="border-t border-line px-[17px] py-3">{{ $subscriptions->links() }}</div>
            @endif
        </div>
        <x-modal wire="showDeleteConfirmation" id="subscriptions-loeschen" title="Subscriptions löschen" persistent>
            <p class="mb-3 text-sm text-ink-base">Diese {{ count($pendingDeletionIds) }} Portal-Einträge endgültig löschen?</p>
            <p class="mb-3 text-sm text-ink-muted">Kunden, Domains, Leistungen und echte Plesk-Webspaces bleiben erhalten.</p>
            <ul class="max-h-72 overflow-y-auto space-y-1 text-sm text-ink-base">
                @foreach ($pendingSubscriptions as $pending)
                    <li wire:key="delete-preview-{{ $pending->id }}">{{ $pending->server->label() }} · {{ $pending->name }}</li>
                @endforeach
            </ul>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button color="secondary" outline wire:click="$set('showDeleteConfirmation', false)">Abbrechen</x-button>
                    <x-button color="red" wire:click="deleteSelected" wire:loading.attr="disabled">Endgültig löschen</x-button>
                </div>
            </x-slot:footer>
        </x-modal>
        <x-column-settings :columns="$this->columnSettings()" />
    </x-page>
</div>
