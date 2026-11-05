<div>
    <x-page title="SEPA-Mandat" subtitle="Rechnungen bequem per Lastschrift begleichen.">
        @if ($createdReference)
            <x-alert color="green" class="mb-4">
                Vielen Dank — Ihr Mandat ist erteilt. Mandatsreferenz: <span class="font-mono">{{ $createdReference }}</span>.
                Künftige Rechnungen buchen wir von Ihrem Konto ab und kündigen jede Abbuchung vorab an.
            </x-alert>
        @endif

        @if ($error)
            <x-alert color="amber" class="mb-4">{{ $error }}</x-alert>
        @endif

        <div class="grid items-start gap-3.5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-3.5">
                <x-panel title="Ihre Mandate">
                    <div class="flex flex-col divide-y divide-line">
                        @forelse ($mandates as $mandate)
                            <div class="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-3" wire:key="mandat-{{ $mandate['id'] }}">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">Mandatsreferenz</span>
                                    <span class="font-mono text-[12.5px] text-ink">{{ $mandate['mandate_reference_number'] }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">Konto</span>
                                    <span class="font-mono text-[12.5px] text-ink-base">{{ $mandate['iban'] ?? '—' }}</span>
                                    <span class="text-[11px] text-ink-muted">{{ $mandate['account_holder'] }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">Erteilt am</span>
                                    <span class="tabular text-[12.5px] text-ink-base">{{ $mandate['signed_date'] ? \Illuminate\Support\Carbon::parse($mandate['signed_date'])->format('d.m.Y') : '—' }}</span>
                                    <span class="text-[11px] text-ink-muted">{{ $mandate['type'] === 'B2B' ? 'Firmenlastschrift' : 'Basislastschrift' }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[12.5px] text-ink-muted">
                                {{ $linked ? 'Es ist noch kein SEPA-Mandat hinterlegt.' : 'Für Ihr Kundenkonto ist die Abrechnung im Portal noch nicht eingerichtet.' }}
                            </p>
                        @endforelse
                    </div>
                </x-panel>

                @if ($canSubmit)
                    @if (! $showForm)
                        <div>
                            <x-button icon="plus" wire:click="$set('showForm', true)">
                                {{ $mandates === [] ? 'Mandat erteilen' : 'Neues Konto hinterlegen' }}
                            </x-button>
                        </div>
                    @else
                        <x-panel title="SEPA-Lastschriftmandat erteilen">
                            <form wire:submit="submit" class="flex flex-col gap-4">
                                <x-input label="Kontoinhaber" wire:model="accountHolder" autocomplete="name" required />

                                <x-input label="IBAN" wire:model="iban" placeholder="DE00 0000 0000 0000 0000 00" autocomplete="off" required />

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-input label="BIC (optional)" wire:model="bic" autocomplete="off" />
                                    <x-input label="Bank (optional)" wire:model="bankName" autocomplete="off" />
                                </div>

                                @if ($isCompany)
                                    <x-select.styled label="Mandatsart"
                                                     wire:model="type"
                                                     :options="[
                                                         ['label' => 'Basislastschrift (SEPA Core)', 'value' => 'BASIC'],
                                                         ['label' => 'Firmenlastschrift (SEPA B2B)', 'value' => 'B2B'],
                                                     ]"
                                                     select="label:label|value:value" />
                                @endif

                                <div class="rounded-[8px] border border-line bg-raised/40 p-3 text-[11.5px] leading-relaxed text-ink-muted">
                                    Ich ermächtige {{ $creditorName }} (Gläubiger-Identifikationsnummer
                                    <span class="font-mono">{{ $creditorId }}</span>), Zahlungen von meinem Konto mittels
                                    Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von
                                    {{ $creditorName }} auf mein Konto gezogenen Lastschriften einzulösen.
                                    <br><br>
                                    Hinweis: Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die
                                    Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem
                                    Kreditinstitut vereinbarten Bedingungen. Die Mandatsreferenz wird mir nach Erteilung
                                    mitgeteilt.
                                </div>

                                <x-checkbox wire:model="consent" label="Ich erteile das SEPA-Lastschriftmandat zu den oben genannten Bedingungen." />

                                <div class="flex gap-2">
                                    <x-button type="submit" loading="submit">Mandat verbindlich erteilen</x-button>
                                    <x-button flat wire:click="$set('showForm', false)">Abbrechen</x-button>
                                </div>
                            </form>
                        </x-panel>
                    @endif
                @endif
            </div>

            <x-panel title="So funktioniert's">
                <ol class="flex list-decimal flex-col gap-2 pl-4 text-[12px] leading-relaxed text-ink-muted">
                    <li>Sie erteilen das Mandat einmalig hier im Portal.</li>
                    <li>Wir kündigen jede Abbuchung vorab an, in der Regel mit der Rechnung.</li>
                    <li>Ein Kontowechsel? Einfach ein neues Konto hinterlegen.</li>
                </ol>
            </x-panel>
        </div>

        @script
        <script>
            $wire.on('sepa-mandat-erteilt', () => $tsui.interaction('toast').success('SEPA-Mandat erteilt').send());
        </script>
        @endscript
    </x-page>
</div>
