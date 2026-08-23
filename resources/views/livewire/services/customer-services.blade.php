<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Monatsumsatz</span>
                <span class="ml-1 font-medium tabular-nums text-gray-800 dark:text-gray-100">
                    {{ $monthlyRevenue->format() }}
                </span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Jahresumsatz</span>
                <span class="ml-1 font-medium tabular-nums text-gray-800 dark:text-gray-100">
                    {{ $yearlyRevenue->format() }}
                </span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Kosten (mtl.)</span>
                <span class="ml-1 font-medium tabular-nums text-gray-800 dark:text-gray-100">
                    {{ $monthlyCosts->format() }}
                </span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Marge (mtl.)</span>
                <span class="ml-1 font-medium tabular-nums {{ $monthlyMargin->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-100' }}">
                    {{ $monthlyMargin->format() }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-toggle wire:model.live="showArchived" label="Archivierte anzeigen" sm />

            <x-button sm
                      icon="plus"
                      :href="route('customer-services.create', $customer)"
                      wire:navigate>
                Leistung anlegen
            </x-button>
        </div>
    </div>

    <div class="divide-y divide-gray-200 dark:divide-dark-600">
        @forelse ($services as $service)
            <div class="py-3" wire:key="service-{{ $service->id }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('customer-services.show', [$customer, $service]) }}"
                               wire:navigate
                               class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                {{ $service->name }}
                            </a>

                            <x-badge :color="$service->status->color()" :text="$service->status->label()" sm />

                            @if ($service->do_not_bill)
                                <x-badge color="amber"
                                         :text="'Nicht abrechnen · '.$service->do_not_bill_reason?->label()"
                                         sm />
                            @endif

                            @foreach ($service->tags as $tag)
                                <x-badge :color="$tag->color" :text="$tag->name" sm />
                            @endforeach
                        </div>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @if ($service->isFromCatalog())
                                {{ $service->product?->name }}@if ($service->productVariant) · {{ $service->productVariant->name }}@endif
                            @else
                                Individuelle Leistung
                            @endif
                            @if ($service->service_start_date)
                                · seit {{ $service->service_start_date->format('d.m.Y') }}
                            @endif
                        </p>
                    </div>

                    <div class="shrink-0 text-sm sm:text-right">
                        <p class="font-medium tabular-nums text-gray-800 dark:text-gray-100">
                            {{ $service->salesPrice()->format() }} · {{ $service->billingInterval()->label() }}
                        </p>
                        <p class="tabular-nums text-gray-500 dark:text-gray-400">
                            EK {{ $service->purchasePrice()->format() }} ·
                            Marge {{ $service->margin()->format() }}
                            @if ($service->marginPercentage() !== null)
                                ({{ number_format($service->marginPercentage(), 1, ',', '.') }} %)
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Für diesen Kunden ist noch keine Leistung erfasst.
            </p>
        @endforelse
    </div>
</div>
