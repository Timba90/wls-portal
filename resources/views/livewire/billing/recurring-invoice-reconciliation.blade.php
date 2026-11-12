<div>
    <x-page title="Serienrechnungen" subtitle="Kundenleistungen im Abgleich mit den Serienrechnungen aus Lexoffice. Lexoffice wird nur gelesen.">
        <x-slot:actions>
            <x-button icon="arrow-path" wire:click="sync" wire:loading.attr="disabled" wire:target="sync">Aus Lexoffice abgleichen</x-button>
        </x-slot:actions>

        @if (! $configured)
            <p class="mb-4 text-sm text-ink-base">
                Bitte zuerst unter <a class="underline" href="{{ route('integrations.index') }}" wire:navigate>Schnittstellen</a> einen Lexoffice-API-Schlüssel hinterlegen.
            </p>
        @endif

        @if ($message)
            <p role="status" class="mb-4 text-sm text-accent">{{ $message }}</p>
        @endif

        @if ($lastRun)
            <p class="mb-4 text-[11.5px] text-ink-faint">
                Letzter Abgleich: {{ \Illuminate\Support\Carbon::parse($lastRun['finished_at'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr
                @if ($lastRun['error'])
                    · <span class="text-red-600">fehlgeschlagen: {{ $lastRun['error'] }}</span>
                @elseif (isset($lastRun['result']))
                    · {{ $lastRun['result']['templates'] }} Serienrechnungen, {{ $lastRun['result']['linked_customers'] }} Kunden und {{ $lastRun['result']['matched_items'] }} Positionen automatisch zugeordnet
                @endif
            </p>
        @endif

        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-kpi-tile label="Aktive Serienrechnungen" :value="$totals['active_templates']" />
            <x-kpi-tile label="Lexoffice netto / Jahr" :value="$totals['invoiced_yearly']->format()" note="Summe aller aktiven Positionen" />
            <x-kpi-tile label="Portal netto / Jahr" :value="$totals['expected_yearly']->format()" note="Abrechenbare Leistungen im Abgleich" />
        </div>

        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
            <nav class="flex flex-wrap gap-2" aria-label="Ansicht">
                @foreach (\App\Livewire\Billing\RecurringInvoiceReconciliation::Views as $key => $label)
                    <button type="button"
                            wire:key="view-{{ $key }}"
                            wire:click="$set('view', '{{ $key }}')"
                            @if ($view === $key) aria-current="page" @endif
                            @class([
                                'cursor-pointer rounded-md border px-3 py-1.5 text-[12px] transition',
                                'border-accent bg-raised text-ink' => $view === $key,
                                'border-line text-ink-muted hover:text-ink' => $view !== $key,
                            ])>
                        {{ $label }} <span class="tabular text-ink-faint">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </nav>
            <div class="lg:ml-auto lg:w-72">
                <x-input wire:model.live.debounce.300ms="search" label="Suche" placeholder="Kunde, Leistung, Position" icon="magnifying-glass" />
            </div>
        </div>

        <x-errors />

        <div class="overflow-hidden rounded-[10px] border border-line bg-panel">
            <div class="overflow-x-auto">
                @switch($view)
                    @case('kunden')
                        <table class="w-full min-w-[760px] text-left text-[12px]">
                            <thead class="border-b border-line bg-raised text-[11px] text-ink-faint">
                                <tr><th class="px-4 py-2.5">Lexoffice-Kontakt</th><th class="px-4 py-2.5">Vorlagen</th><th class="px-4 py-2.5">Netto / Jahr</th><th class="px-4 py-2.5">Portal-Kunde</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $contact)
                                    <tr wire:key="contact-{{ $contact['contact_id'] }}" class="border-b border-line align-top">
                                        <td class="px-4 py-3 text-ink-base">{{ $contact['name'] }}</td>
                                        <td class="px-4 py-3 tabular text-ink-muted">{{ $contact['templates'] }}</td>
                                        <td class="px-4 py-3 tabular text-ink-muted">{{ $contact['yearly']->format() }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-end gap-2">
                                                <div class="w-80">
                                                    <x-select.styled wire:model="contactLinks.{{ $contact['contact_id'] }}" placeholder="Kunde auswählen" searchable :options="$customerOptions" select="label:name|value:id" />
                                                </div>
                                                <x-button sm wire:click="linkContact('{{ $contact['contact_id'] }}')" wire:loading.attr="disabled">Verknüpfen</x-button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-10 text-center text-ink-faint">Alle Lexoffice-Kontakte mit aktiven Serienrechnungen sind einem Kunden zugeordnet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('positionen')
                    @case('ignoriert')
                        <table class="w-full min-w-[900px] text-left text-[12px]">
                            <thead class="border-b border-line bg-raised text-[11px] text-ink-faint">
                                <tr><th class="px-4 py-2.5">Kunde</th><th class="px-4 py-2.5">Position</th><th class="px-4 py-2.5">Betrag</th><th class="px-4 py-2.5">{{ $view === 'positionen' ? 'Leistung zuordnen' : '' }}</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $item)
                                    <tr wire:key="item-{{ $item->id }}" class="border-b border-line align-top">
                                        <td class="px-4 py-3">
                                            @if ($item->template->customer)
                                                <a href="{{ route('customers.show', $item->template->customer) }}" wire:navigate class="text-ink-base hover:text-accent">{{ $item->template->customer->displayName() }}</a>
                                            @else
                                                <span class="text-ink-base">{{ $item->template->contact_name }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="block text-ink-base">{{ $item->name }}</span>
                                            @if ($item->description)
                                                <span class="mt-1 block whitespace-pre-line text-[11px] text-ink-faint">{{ \Illuminate\Support\Str::limit($item->description, 160) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 tabular text-ink-muted">{{ $item->lineNet()->format() }} {{ $item->template->execution_interval->label() }}</td>
                                        <td class="px-4 py-3">
                                            @if ($view === 'positionen')
                                                <div class="flex items-end gap-2">
                                                    <div class="w-80">
                                                        <x-select.styled wire:model="itemAssignments.{{ $item->id }}" placeholder="Leistung auswählen" searchable :options="$serviceOptions[$item->template->customer_id] ?? []" select="label:name|value:id" />
                                                    </div>
                                                    <x-button sm wire:click="assignItem({{ $item->id }})" wire:loading.attr="disabled">Zuordnen</x-button>
                                                    <x-button sm color="secondary" outline wire:click="ignoreItem({{ $item->id }})" title="Position bewusst keiner Leistung zuordnen">Ignorieren</x-button>
                                                </div>
                                            @else
                                                <x-button sm color="secondary" outline wire:click="restoreItem({{ $item->id }})">Wieder abgleichen</x-button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-10 text-center text-ink-faint">Keine Positionen in dieser Ansicht.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @default
                        <table class="w-full min-w-[960px] text-left text-[12px]">
                            <thead class="border-b border-line bg-raised text-[11px] text-ink-faint">
                                <tr><th class="px-4 py-2.5">Kunde</th><th class="px-4 py-2.5">Leistung</th><th class="px-4 py-2.5">Portal</th><th class="px-4 py-2.5">Lexoffice-Positionen</th><th class="px-4 py-2.5">Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php($service = $row['service'])
                                    <tr wire:key="service-{{ $service->id }}" class="border-b border-line align-top">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('customers.show', $service->customer) }}" wire:navigate class="text-ink-base hover:text-accent">{{ $service->customer->displayName() }}</a>
                                            @unless ($service->customer->lexoffice_contact_id)
                                                <span class="mt-1 block text-[11px] text-ink-faint">nicht mit Lexoffice verknüpft</span>
                                            @endunless
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('customer-services.show', [$service->customer, $service]) }}" wire:navigate class="block text-ink-base hover:text-accent">{{ $service->name }}</a>
                                            @if ($service->billing_label && $service->billing_label !== $service->name)
                                                <span class="mt-1 block text-[11px] text-ink-faint">{{ $service->billing_label }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 tabular text-ink-muted">
                                            {{ $service->salesPrice()->format() }} {{ $service->billingInterval()->label() }}
                                            <span class="mt-1 block text-[11px] text-ink-faint">{{ $row['expected_yearly']->format() }} / Jahr · {{ $service->status->label() }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @forelse ($row['items'] as $item)
                                                <div wire:key="service-{{ $service->id }}-item-{{ $item->id }}" class="mb-1.5 flex items-start justify-between gap-2">
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-ink-base">{{ $item->name }}</span>
                                                        <span class="block tabular text-[11px] text-ink-faint">
                                                            {{ $item->lineNet()->format() }} {{ $item->template->execution_interval->label() }}
                                                            · {{ $item->match_source === \App\Models\LexofficeRecurringTemplateItem::MatchedManually ? 'von Hand' : 'automatisch' }}
                                                        </span>
                                                    </span>
                                                    <button type="button" class="cursor-pointer text-[11px] text-ink-faint hover:text-red-600" wire:click="releaseItem({{ $item->id }})" wire:confirm="Zuordnung dieser Position lösen?">lösen</button>
                                                </div>
                                            @empty
                                                <span class="text-ink-faint">—</span>
                                            @endforelse
                                            @if ($row['items']->isNotEmpty())
                                                <span class="block tabular text-[11px] text-ink-faint">{{ $row['invoiced_yearly']->format() }} / Jahr</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3"><x-status-pill :kind="$row['status']->pillKind()" :label="$row['status']->label()" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint">Keine Leistungen in dieser Ansicht.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                @endswitch
            </div>
        </div>
    </x-page>
</div>
