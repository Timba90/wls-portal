@props(['service' => null, 'showService' => true])

@if ($service)
    @php
        if ($service->status !== \App\Enums\CustomerServiceStatus::Active) {
            $label = $service->status->label();
            $kind = $service->status->pillKind();
        } elseif ($service->do_not_bill) {
            $label = 'Nicht abrechnen';
            $kind = 'warn';
        } elseif (!$service->billingInterval()->isRecurring()) {
            $label = 'Einmalig';
            $kind = 'info';
        } else {
            $label = 'Abrechenbar';
            $kind = 'ok';
        }
    @endphp
    <a href="{{ route('customer-services.show', [$service->customer_id, $service]) }}"
       wire:navigate
       class="flex min-w-0 flex-col items-start gap-1"
       title="Abrechnungseinstellung der verknüpften Leistung; kein Nachweis einer erstellten Rechnung.">
        <x-status-pill :kind="$kind" :label="$label" />
        @if ($showService)
            <span class="max-w-full truncate text-[10.5px] text-ink-muted">{{ $service->billing_label ?: $service->name }}</span>
        @endif
    </a>
@else
    <span title="Ohne verknüpfte Leistung ist die Abrechnung nicht bestimmbar. Die Kosten können in einem Paket enthalten sein.">
        <x-status-pill kind="mute" label="Keine Leistung zugeordnet" />
    </span>
@endif
