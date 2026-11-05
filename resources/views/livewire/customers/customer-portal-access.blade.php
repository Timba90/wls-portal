<div class="flex flex-col gap-3.5">
    <div class="rounded-[10px] border border-line bg-panel p-[17px]">
        <div class="mb-3 flex items-baseline justify-between gap-3">
            <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Flux-Verknüpfung</span>
            @if ($customer->isLinkedToFlux())
                <x-status-pill kind="ok" label="Verknüpft" />
            @else
                <x-status-pill kind="warn" label="Nicht verknüpft" />
            @endif
        </div>

        <p class="mb-3 text-[11.5px] text-ink-muted">
            Rechnungen und SEPA-Mandate liest das Kundenportal aus Flux. Dafür braucht es die ID des Kontakts in Flux.
        </p>

        <form wire:submit="saveFluxContact" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="sm:w-56">
                <x-input label="Flux-Kontakt-ID" wire:model="fluxContactId" inputmode="numeric" placeholder="z. B. 190" />
            </div>
            <x-button type="submit" loading="saveFluxContact" :disabled="$customer->isArchived()">Speichern</x-button>
        </form>
    </div>

    <div class="rounded-[10px] border border-line bg-panel p-[17px]">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-3">
            <span class="text-[9.5px] font-semibold uppercase tracking-[0.11em] text-ink-faint">Portalzugänge</span>
            <span class="flex items-center gap-3">
                <a href="{{ route('tickets.index', ['kunde' => $customer->id]) }}" wire:navigate class="text-[11px] text-accent hover:underline">Tickets ansehen</a>
                @unless ($customer->isArchived())
                    <x-button sm icon="plus" wire:click="$toggle('showInviteForm')">Zugang einladen</x-button>
                @endunless
            </span>
        </div>

        @if ($showInviteForm)
            <form wire:submit="invite" class="mb-4 grid gap-3 rounded-[8px] border border-line bg-raised/40 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <x-input label="Name" wire:model="inviteName" required />
                <x-input label="E-Mail-Adresse" wire:model="inviteEmail" type="email" required />
                <x-button type="submit" loading="invite">Einladen</x-button>
            </form>
        @endif

        <div class="flex flex-col divide-y divide-line">
            @forelse ($portalUsers as $portalUser)
                <div class="flex flex-col gap-2 py-2.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                     wire:key="zugang-{{ $portalUser->id }}">
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-[12.5px] text-ink-base">{{ $portalUser->name }}</span>
                        <span class="truncate font-mono text-[10.5px] text-ink-faint">
                            {{ $portalUser->email }}
                            · {{ $portalUser->last_login_at ? 'zuletzt angemeldet '.$portalUser->last_login_at->format('d.m.Y H:i') : 'noch nie angemeldet' }}
                        </span>
                    </span>

                    <span class="flex flex-none items-center gap-2">
                        @if (! $portalUser->is_active)
                            <x-status-pill kind="mute" label="Gesperrt" />
                        @elseif (! $portalUser->hasPassword())
                            <x-status-pill kind="info" label="Eingeladen" />
                        @else
                            <x-status-pill kind="ok" label="Aktiv" />
                        @endif

                        @if ($portalUser->is_active)
                            <x-button flat sm wire:click="resendInvitation({{ $portalUser->id }})">
                                {{ $portalUser->hasPassword() ? 'Passwort-Link' : 'Erneut einladen' }}
                            </x-button>
                        @endif

                        <x-button flat sm wire:click="toggleActive({{ $portalUser->id }})">
                            {{ $portalUser->is_active ? 'Sperren' : 'Entsperren' }}
                        </x-button>
                    </span>
                </div>
            @empty
                <p class="text-[12px] text-ink-muted">Noch keine Zugänge. Eingeladene Personen legen ihr Passwort selbst fest.</p>
            @endforelse
        </div>

        <p class="mt-3 border-t border-line pt-3 text-[11px] text-ink-faint">
            Anmeldung für Kunden: <span class="font-mono">{{ $portalUrl }}</span>
        </p>
    </div>

    @script
    <script>
        $wire.on('flux-kontakt-gespeichert', () => $tsui.interaction('toast').success('Flux-Verknüpfung gespeichert').send());
        $wire.on('zugang-eingeladen', () => $tsui.interaction('toast').success('Einladung verschickt').send());
        $wire.on('einladung-versendet', () => $tsui.interaction('toast').success('Link verschickt').send());
    </script>
    @endscript
</div>
