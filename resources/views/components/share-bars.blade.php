@props(['shares', 'empty' => 'Nichts abzurechnen.'])

{{--
    Woraus sich ein Betrag zusammensetzt: je Zeile ein Anteil mit Balken.

    Die Zeilen tragen ihre Zahlen als Text, deshalb braucht es hier keine
    zweite Fassung für Screenreader — nur der Balken selbst ist Zierde.
--}}
@php
    $groesster = collect($shares)->max(fn (array $anteil): int => $anteil['amount']->cents) ?: 1;
@endphp

<div class="flex flex-col gap-3">
    @forelse ($shares as $anteil)
        <div wire:key="anteil-{{ Str::slug($anteil['label']) }}">
            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <span class="truncate text-[12.5px] text-ink-base">{{ $anteil['label'] }}</span>

                <span class="flex flex-none items-baseline gap-2">
                    <span class="tabular text-[12.5px] text-ink">{{ $anteil['amount']->format() }}</span>
                    <span class="tabular w-11 text-right text-[10.5px] text-ink-faint">
                        {{ number_format($anteil['share'], 1, ',', '.') }} %
                    </span>
                </span>
            </div>

            <div class="h-1.5 w-full overflow-hidden rounded-full bg-raised" aria-hidden="true">
                <div class="h-full rounded-full bg-[color:var(--accent)]"
                     style="width: {{ max(1, round($anteil['amount']->cents / $groesster * 100)) }}%"></div>
            </div>
        </div>
    @empty
        <p class="py-[26px] text-center text-[11.5px] text-ink-faint">{{ $empty }}</p>
    @endforelse
</div>
