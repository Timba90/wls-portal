<div class="flex flex-col gap-3.5">
    {{--
        Die Summen stehen in der Kopfkarte des Kundendetails; hier bleiben nur
        die Bedienelemente, damit dieselbe Zahl nicht zweimal auf dem Schirm
        steht.
    --}}
    <div class="flex flex-wrap items-center justify-end gap-3">
        <x-toggle wire:model.live="showArchived" label="Archivierte anzeigen" sm />

        <x-button sm
                  icon="plus"
                  :href="route('customer-services.create', $customer)"
                  wire:navigate>
            Leistung anlegen
        </x-button>
    </div>

    <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
        <div class="overflow-x-auto">
            <div class="min-w-[640px]">
                <div class="grid grid-cols-[1.9fr_0.9fr_0.9fr_1fr_0.9fr] gap-3 border-b border-line bg-raised px-4 py-2.5">
                    @foreach (['Leistung', 'Turnus', 'Preis', 'Beginn', 'Status'] as $spalte)
                        <span @class([
                            'truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint',
                            'text-right' => $spalte === 'Preis',
                        ])>{{ $spalte }}</span>
                    @endforeach
                </div>

                @forelse ($services as $service)
                    <a href="{{ route('customer-services.show', [$customer, $service]) }}"
                       wire:navigate
                       wire:key="service-{{ $service->id }}"
                       class="grid grid-cols-[1.9fr_0.9fr_0.9fr_1fr_0.9fr] items-center gap-3 border-b border-line px-4 py-3 transition hover:bg-raised focus-visible:bg-raised focus-visible:outline-none">
                        <div class="flex min-w-0 items-center gap-2.5">
                            {{-- Statuspunkt in der Farbe der Statusplakette. --}}
                            <span class="h-[7px] w-[7px] flex-none rounded-full"
                                  style="background: var(--pill-{{ $service->status->pillKind() }}-ink)"></span>

                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-[12.5px] font-medium text-ink-base">{{ $service->name }}</span>
                                <span class="truncate text-[10.5px] text-ink-faint">
                                    {{ $service->product?->name ?? 'Individuelle Leistung' }}
                                </span>
                            </span>
                        </div>

                        <span class="truncate text-[12px] text-ink-muted">{{ $service->billingInterval()->label() }}</span>

                        <span class="truncate tabular text-right text-[12.5px] text-ink">
                            {{ $service->salesPrice()->format() }}
                        </span>

                        <span class="truncate text-[12px] text-ink-muted">
                            {{ $service->service_start_date?->format('d.m.Y') ?? '—' }}
                        </span>

                        <span class="flex flex-wrap items-center gap-1.5">
                            <x-status-pill :kind="$service->status->pillKind()" :label="$service->status->label()" />

                            @if ($service->do_not_bill)
                                <x-status-pill kind="warn" label="Nicht abrechnen" :dot="false" />
                            @endif
                        </span>
                    </a>
                @empty
                    <div class="px-4 py-[34px] text-center text-[12.5px] text-ink-faint">
                        Für diesen Kunden ist noch keine Leistung hinterlegt.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
