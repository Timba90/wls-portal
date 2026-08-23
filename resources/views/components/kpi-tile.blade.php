@props(['label', 'value', 'note' => null, 'href' => null])

{{-- Kennzahlkachel des Entwurfs: Label, große Mono-Zahl, Fußnote. --}}
@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'flex flex-col gap-2 rounded-[10px] border border-line bg-panel px-4 py-4',
        'transition hover:border-line-strong' => (bool) $href,
    ]) }}>
    <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">{{ $label }}</span>
    <span class="tabular text-[24px] font-semibold leading-none text-ink">{{ $value }}</span>

    @if ($note)
        <span class="text-[11px] text-ink-muted">{{ $note }}</span>
    @endif
</{{ $tag }}>
