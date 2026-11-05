<div>
    <x-page :title="$ticket->subject"
            :subtitle="$ticket->ticket_number.' · eröffnet '.$ticket->created_at->format('d.m.Y H:i')"
            back-label="Tickets ／ zurück zur Liste"
            :back-url="route('customer-portal.tickets.index')">
        <x-slot:actions>
            <x-status-pill :kind="$ticket->status->pillKind()" :label="$ticket->status->label()" />
            @if ($ticket->isOpen())
                <x-button flat icon="check" wire:click="markResolved" wire:confirm="Ticket als erledigt markieren?">Erledigt</x-button>
            @endif
        </x-slot:actions>

        <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-3.5">
                @foreach ($ticket->publicMessages as $message)
                    <article wire:key="nachricht-{{ $message->id }}"
                             @class([
                                 'rounded-[10px] border px-4 py-3',
                                 'border-line bg-panel' => ! $message->isFromTeam(),
                                 'border-accent/40 bg-raised' => $message->isFromTeam(),
                             ])>
                        <header class="mb-2 flex items-baseline justify-between gap-3">
                            <span class="text-[12px] font-medium text-ink">
                                {{ $message->authorName() }}
                                @if ($message->isFromTeam())
                                    <span class="ml-1 text-[10.5px] font-normal text-accent">{{ config('portal.brand.name') }}</span>
                                @endif
                            </span>
                            <span class="tabular text-[10.5px] text-ink-faint">{{ $message->created_at->format('d.m.Y H:i') }}</span>
                        </header>
                        <div class="whitespace-pre-line text-[12.5px] leading-relaxed text-ink-base">{{ $message->body }}</div>
                    </article>
                @endforeach

                <x-panel title="Antworten">
                    <form wire:submit="sendReply" class="flex flex-col gap-3">
                        <x-textarea wire:model="reply" rows="5" placeholder="Ihre Nachricht …" required />
                        <div>
                            <x-button type="submit" icon="paper-airplane" loading="sendReply">
                                {{ $ticket->isOpen() ? 'Antwort senden' : 'Antworten und Ticket wieder öffnen' }}
                            </x-button>
                        </div>
                    </form>
                </x-panel>
            </div>

            <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Details</span>
                @foreach ([
                    'Ticketnummer' => $ticket->ticket_number,
                    'Status' => $ticket->status->label(),
                    'Dringlichkeit' => $ticket->priority->label(),
                    'Projekt' => $ticket->project?->name,
                    'Eingegangen über' => $ticket->source->label(),
                    'Letzte Aktivität' => $ticket->last_activity_at?->format('d.m.Y H:i'),
                ] as $label => $wert)
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-[11.5px] text-ink-muted">{{ $label }}</span>
                        <span class="truncate text-right text-[12px] text-ink-base">{{ blank($wert) ? '—' : $wert }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @script
        <script>
            $wire.on('antwort-gesendet', () => $tsui.interaction('toast').success('Antwort gesendet').send());
        </script>
        @endscript
    </x-page>
</div>
