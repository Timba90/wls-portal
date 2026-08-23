@props([
    'label',
    'icon' => null,
    'route' => null,
    'match' => null,
    'href' => null,
    'count' => null,
])

@php
    $target = $href ?? ($route ? route($route) : '#');
    $active = $route
        ? request()->routeIs($match ?? $route)
        : false;
@endphp

{{-- Navigationseintrag: aktiver Zustand mit Akzentfarbe und leicht erhöhter Fläche. --}}
<a href="{{ $target }}"
   @if ($route) wire:navigate @endif
   @class([
       'group flex items-center gap-2.5 rounded-md px-2 py-[7px] text-[12px] transition',
       'bg-raised font-medium text-ink' => $active,
       'text-ink-muted hover:bg-raised/60 hover:text-ink-base' => ! $active,
   ])>
    @if ($icon)
        <x-icon :name="$icon" @class([
            'h-4 w-4 flex-none',
            'text-accent' => $active,
            'text-ink-faint group-hover:text-ink-muted' => ! $active,
        ]) />
    @endif

    <span class="flex-1 truncate">{{ $label }}</span>

    @if (! is_null($count))
        <span class="font-mono text-[10px] tabular-nums text-ink-faint">{{ $count }}</span>
    @endif
</a>
