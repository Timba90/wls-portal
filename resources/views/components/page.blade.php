@props([
    'title',
    'subtitle' => null,
    'backLabel' => null,
    'backUrl' => null,
])

{{--
    Seitengerüst des Arbeitsbereichs: sticky Kopfzeile plus Inhaltsfläche mit
    einheitlichem Innenabstand.
--}}
<x-page-header :title="$title" :subtitle="$subtitle" :back-label="$backLabel" :back-url="$backUrl">
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-page-header>

<div {{ $attributes->class(['flex-1 px-4 py-5 sm:px-6']) }}>
    {{ $slot }}
</div>
