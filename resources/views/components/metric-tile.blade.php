@props(['label', 'value', 'href' => null])

{{-- Kennzahlkachel des Dashboards. --}}
@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'block rounded-lg border border-line bg-white p-4 shadow-sm  ',
        'transition hover:border-primary-300 dark:hover:border-primary-700' => (bool) $href,
    ]) }}>
    <p class="text-sm text-ink-muted">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-ink">{{ $value }}</p>
</{{ $tag }}>
