<div>
    @if (! $lesbar)
        {{--
            Kein Fehler, sondern ein Zustand — aus einem von zwei Gründen:
            zu dieser Domain gehört gar kein Anschluss, oder der Anschluss
            ihres Anbieters kennt keinen lesenden Aufruf für Zonen.
        --}}
        <div class="rounded-[8px] border border-line bg-raised px-3.5 py-3">
            @if ($vonHand)
                <p class="text-[12.5px] text-ink-base">
                    Diese Domain wird von Hand gepflegt.
                </p>
                <p class="mt-1 text-[11.5px] text-ink-faint">
                    Zu ihr gehört kein Anschluss, der eine Zone lesen könnte. Ihre Einträge stehen
                    bei dem Registrar, bei dem sie liegt.
                </p>
            @else
                <p class="text-[12.5px] text-ink-base">
                    {{ $domain->provider->label() }} liefert hier keine DNS-Zone.
                </p>
                <p class="mt-1 text-[11.5px] text-ink-faint">
                    Der Anschluss dieses Anbieters kennt keinen lesenden Aufruf für Zonen. Geraten
                    wird keiner — ein falscher Name kann eine echte Zone ändern.
                </p>
            @endif
        </div>
    @else
        <div class="mb-3.5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                @if ($zone)
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach ($typen as $typ => $anzahl)
                            <button type="button"
                                    wire:click="$set('filterType', '{{ $filterType === $typ ? '' : $typ }}')"
                                    @class([
                                        'rounded-[5px] border px-2 py-[3px] font-mono text-[10.5px] transition',
                                        'border-accent text-accent' => $filterType === $typ,
                                        'border-line text-ink-muted hover:text-ink-base' => $filterType !== $typ,
                                    ])>
                                {{ $typ }} {{ $anzahl }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-button sm
                      color="secondary"
                      outline
                      icon="arrow-path"
                      wire:click="neuLaden"
                      wire:loading.attr="disabled"
                      wire:target="neuLaden">
                <span wire:loading.remove wire:target="neuLaden">Neu laden</span>
                <span wire:loading wire:target="neuLaden">Wird gelesen …</span>
            </x-button>
        </div>

        @if ($fehler)
            <div class="rounded-[8px] border border-[color:var(--pill-bad-line)] bg-[color:var(--pill-bad-bg)] px-3.5 py-3">
                <p class="text-[12.5px] text-[color:var(--pill-bad-ink)]">Die Zone konnte nicht gelesen werden.</p>
                <p class="mt-1 text-[11.5px] text-ink-muted">{{ $fehler }}</p>
            </div>
        @elseif (! $zone)
            <p class="py-6 text-center text-[12px] text-ink-faint">Keine Zone gefunden.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-line">
                            <th class="pb-2 pr-3 text-[10.5px] font-medium uppercase tracking-wide text-ink-faint">Name</th>
                            <th class="pb-2 pr-3 text-[10.5px] font-medium uppercase tracking-wide text-ink-faint">Typ</th>
                            <th class="pb-2 pr-3 text-[10.5px] font-medium uppercase tracking-wide text-ink-faint">Wert</th>
                            <th class="pb-2 pr-3 text-right text-[10.5px] font-medium uppercase tracking-wide text-ink-faint">Prio</th>
                            <th class="pb-2 text-right text-[10.5px] font-medium uppercase tracking-wide text-ink-faint">TTL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($records as $record)
                            <tr wire:key="dns-{{ $loop->index }}">
                                <td class="py-2 pr-3 align-top">
                                    <span class="font-mono text-[11.5px] text-ink-base">{{ $record->name === '' ? '@' : $record->name }}</span>

                                    @if ($record->derived)
                                        {{--
                                            autoDNS führt die Haupt-IP getrennt von den
                                            Einträgen. Im DNS gilt sie trotzdem, in der
                                            Zonenliste des Anbieters steht sie nicht.
                                        --}}
                                        <span class="ml-1.5 text-[10px] text-ink-faint"
                                              title="Vom Anbieter aus der Haupt-IP der Zone erzeugt, kein eigener Eintrag">abgeleitet</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 align-top">
                                    <span class="font-mono text-[11.5px] text-ink-muted">{{ $record->type }}</span>
                                </td>
                                <td class="py-2 pr-3 align-top">
                                    <span class="break-all font-mono text-[11.5px] text-ink-base">{{ $record->content }}</span>
                                </td>
                                <td class="py-2 pr-3 text-right align-top">
                                    <span class="tabular text-[11.5px] text-ink-muted">{{ $record->priority ?? '—' }}</span>
                                </td>
                                <td class="py-2 text-right align-top">
                                    <span class="tabular text-[11.5px] text-ink-muted">{{ $record->ttl ?? '—' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-[12px] text-ink-faint">
                                    @if ($filterType !== '')
                                        Keine {{ $filterType }}-Einträge.
                                    @else
                                        Die Zone enthält keine Einträge.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3.5 flex flex-wrap gap-x-4 gap-y-1 border-t border-line pt-3 text-[11px] text-ink-faint">
                <span>Ursprung <span class="font-mono text-ink-muted">{{ $zone->origin }}</span></span>

                @if ($zone->ttl !== null)
                    <span>Vorgabe-TTL <span class="tabular text-ink-muted">{{ $zone->ttl }}</span> s</span>
                @endif

                @if ($zone->soaEmail)
                    <span>Zonenkontakt <span class="font-mono text-ink-muted">{{ $zone->soaEmail }}</span></span>
                @endif

                @if ($zone->updatedAt)
                    <span>Beim Anbieter geändert {{ $zone->updatedAt->format('d.m.Y H:i') }}</span>
                @endif
            </div>
        @endif
    @endif
</div>
