<div>
    <x-page title="Rechnungen" subtitle="Alle Rechnungen und Gutschriften als PDF zum Herunterladen.">
        @if (! $linked)
            <x-panel>
                <p class="text-[12.5px] text-ink-muted">
                    Für Ihr Kundenkonto ist die Abrechnung im Portal noch nicht eingerichtet.
                    Ihre Rechnungen erhalten Sie bis dahin wie gewohnt per E-Mail.
                </p>
            </x-panel>
        @else
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <x-kpi-tile label="Offen" :value="$openAmount->format()" :note="$openCount.' offene '.($openCount === 1 ? 'Rechnung' : 'Rechnungen')" />
                <x-kpi-tile label="Rechnungen {{ now()->year }}" :value="$yearAmount->format()" note="Brutto, inklusive Gutschriften" />
                <x-kpi-tile label="Belege gesamt" :value="$totalCount" note="Seit Beginn der Zusammenarbeit" />
            </div>

            @if ($error)
                <x-alert color="amber" class="mb-4">{{ $error }}</x-alert>
            @endif

            <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="sm:w-72">
                    <x-input wire:model.live.debounce.300ms="search" label="Suche" placeholder="Rechnungsnummer" icon="magnifying-glass" />
                </div>

                <div class="flex gap-1 rounded-[9px] border border-line bg-panel p-1">
                    @foreach (['alle' => 'Alle', 'offen' => 'Offen', 'bezahlt' => 'Bezahlt'] as $schluessel => $beschriftung)
                        <button type="button"
                                wire:click="$set('state', '{{ $schluessel }}')"
                                @class([
                                    'whitespace-nowrap rounded-[6px] px-3 py-1.5 text-[12px] font-medium transition',
                                    'bg-accent text-accent-ink' => $state === $schluessel,
                                    'text-ink-muted hover:bg-raised hover:text-ink-base' => $state !== $schluessel,
                                ])>{{ $beschriftung }}</button>
                    @endforeach
                </div>

                <div class="sm:ml-auto">
                    <x-button flat icon="arrow-path" wire:click="refreshInvoices" loading="refreshInvoices">Aktualisieren</x-button>
                </div>
            </div>

            <x-panel :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-[12.5px]">
                        <thead>
                            <tr class="border-b border-line text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">
                                <th class="px-4 py-2.5">Rechnung</th>
                                <th class="px-4 py-2.5">Datum</th>
                                <th class="px-4 py-2.5">Fällig</th>
                                <th class="px-4 py-2.5 text-right">Brutto</th>
                                <th class="px-4 py-2.5 text-right">Offen</th>
                                <th class="px-4 py-2.5">Stand</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($invoices as $invoice)
                                <tr wire:key="rechnung-{{ $invoice['id'] }}">
                                    <td class="px-4 py-2.5 font-mono text-ink-base">{{ $invoice['invoice_number'] }}</td>
                                    <td class="tabular px-4 py-2.5 text-ink-muted">{{ $invoice['invoice_date'] ? \Illuminate\Support\Carbon::parse($invoice['invoice_date'])->format('d.m.Y') : '—' }}</td>
                                    <td class="tabular px-4 py-2.5 text-ink-muted">{{ $invoice['payment_target_date'] ? \Illuminate\Support\Carbon::parse($invoice['payment_target_date'])->format('d.m.Y') : '—' }}</td>
                                    <td class="tabular px-4 py-2.5 text-right text-ink">{{ $invoice['gross']->format() }}</td>
                                    <td class="tabular px-4 py-2.5 text-right text-ink-muted">{{ ($invoice['state']?->isOutstanding() ?? false) ? $invoice['balance_money']->format() : '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($invoice['state'])
                                            <x-status-pill :kind="$invoice['state']->pillKind()" :label="$invoice['state']->label()" />
                                        @else
                                            <span class="text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="{{ route('customer-portal.invoices.download', $invoice['id']) }}"
                                           class="inline-flex items-center gap-1.5 text-[12px] text-accent hover:underline">
                                            <x-icon name="arrow-down-tray" class="h-4 w-4" />
                                            PDF
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-ink-muted">
                                        {{ $error ? '—' : 'Keine Rechnungen gefunden.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-panel>
        @endif
    </x-page>
</div>
