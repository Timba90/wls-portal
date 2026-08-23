@props(['label', 'value', 'href' => null])

{{-- Kennzahlkachel des Dashboards. --}}
@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'block rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-dark-600 dark:bg-dark-700',
        'transition hover:border-primary-300 dark:hover:border-primary-700' => (bool) $href,
    ]) }}>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $value }}</p>
</{{ $tag }}>
