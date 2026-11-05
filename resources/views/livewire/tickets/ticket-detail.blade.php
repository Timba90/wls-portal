<div>
    <x-page :title="$ticket->subject"
            :subtitle="$ticket->ticket_number.' · '.$ticket->customer->displayName().' · '.$ticket->source->label()"
            back-label="Tickets ／ zurück zur Liste"
            :back-url="route('tickets.index')">
        <x-slot:actions>
            <x-status-pill :kind="$ticket->priority->pillKind()" :label="$ticket->priority->label()" :dot="false" />
            <x-status-pill :kind="$ticket->status->pillKind()" :label="$ticket->status->label()" />
            @if ($ticket->assigned_user_id !== auth()->id())
                <x-button flat icon="hand-raised" wire:click="assignToMe">Übernehmen</x-button>
            @endif
        </x-slot:actions>

        <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-3.5">
                @foreach ($ticket->messages as $message)
                    <article wire:key="nachricht-{{ $message->id }}"
                             @class([
                                 'rounded-[10px] border px-4 py-3',
                                 'border-line bg-panel' => $message->isFromCustomer() || ! $message->author_type,
                                 'border-accent/40 bg-raised' => $message->isFromTeam() && ! $message->is_internal,
                                 'border-dashed border-[color:var(--pill-warn-line)] bg-[color:var(--pill-warn-bg)]' => $message->is_internal,
                             ])>
                        <header class="mb-2 flex items-baseline justify-between gap-3">
                            <span class="text-[12px] font-medium text-ink">
                                {{ $message->authorName() }}
                                <span class="ml-1 text-[10.5px] font-normal text-ink-faint">
                                    @if ($message->is_internal)
                                        interne Notiz
                                    @elseif ($message->isFromCustomer())
                                        Kunde
                                    @elseif ($message->isFromTeam())
                                        Team
                                    @else
                                        automatisch
                                    @endif
                                </span>
                            </span>
                            <span class="tabular text-[10.5px] text-ink-faint">{{ $message->created_at->format('d.m.Y H:i') }}</span>
                        </header>
                        <div class="whitespace-pre-line text-[12.5px] leading-relaxed text-ink-base">{{ $message->body }}</div>
                    </article>
                @endforeach

                <x-panel title="Antworten">
                    <form wire:submit="sendReply" class="flex flex-col gap-3">
                        <x-textarea wire:model="reply" rows="5" placeholder="Antwort an den Kunden oder interne Notiz …" required />
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <x-toggle wire:model.live="internal" label="Interne Notiz (für den Kunden unsichtbar)" sm />
                            <x-button type="submit" icon="paper-airplane" loading="sendReply">
                                {{ $internal ? 'Notiz speichern' : 'An Kunden senden' }}
                            </x-button>
                        </div>
                    </form>
                </x-panel>
            </div>

            <div class="flex min-w-0 flex-col gap-3.5">
                <x-panel title="Bearbeitung">
                    <form wire:submit="saveDetails" class="flex flex-col gap-3">
                        <x-select.styled label="Status" wire:model="status" :options="$statusOptions" select="label:label|value:value" />
                        <x-select.styled label="Priorität" wire:model="priority" :options="$priorityOptions" select="label:label|value:value" />
                        <x-select.styled label="Zuständig" wire:model="assignedUserId" placeholder="Niemand" :options="$users" select="label:label|value:value" />
                        <x-select.styled label="Projekt" wire:model="projectId" placeholder="Kein Projekt" :options="$projects" select="label:label|value:value" />
                        <x-button type="submit" loading="saveDetails">Speichern</x-button>
                    </form>
                </x-panel>

                <div class="flex flex-col gap-[11px] rounded-[10px] border border-line bg-panel px-4 py-[15px]">
                    <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Details</span>
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-[11.5px] text-ink-muted">Kunde</span>
                        <a href="{{ route('customers.show', $ticket->customer) }}" wire:navigate class="truncate text-right text-[12px] text-accent hover:underline">
                            {{ $ticket->customer->displayName() }}
                        </a>
                    </div>
                    @foreach ([
                        'Eröffnet von' => $ticket->portalUser ? $ticket->portalUser->name.' ('.$ticket->portalUser->email.')' : null,
                        'Herkunft' => $ticket->source->label(),
                        'Externe Referenz' => $ticket->external_reference,
                        'Eröffnet' => $ticket->created_at->format('d.m.Y H:i'),
                        'Letzte Aktivität' => $ticket->last_activity_at?->format('d.m.Y H:i'),
                        'Geschlossen' => $ticket->closed_at?->format('d.m.Y H:i'),
                    ] as $label => $wert)
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[11.5px] text-ink-muted">{{ $label }}</span>
                            <span class="truncate text-right text-[12px] text-ink-base">{{ blank($wert) ? '—' : $wert }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-[10px] border border-line bg-panel p-[17px]">
                    <livewire:shared.audit-panel :auditable="$ticket" :key="'historie-'.$ticket->id" />
                </div>
            </div>
        </div>

        @script
        <script>
            $wire.on('nachricht-gespeichert', () => $tsui.interaction('toast').success('Gespeichert').send());
            $wire.on('ticket-gespeichert', () => $tsui.interaction('toast').success('Ticket gespeichert').send());
        </script>
        @endscript
    </x-page>
</div>
