@props(['initials', 'size' => 'md'])

{{-- Initialen-Kachel wie in den Tabellen und der Kundenkopfzeile des Entwurfs. --}}
@php
    $classes = match ($size) {
        'lg' => 'h-12 w-12 rounded-[10px] text-[15px]',
        'sm' => 'h-6 w-6 rounded-[6px] text-[9.5px]',
        default => 'h-8 w-8 rounded-[8px] text-[11px]',
    };
@endphp

<span {{ $attributes->class([
    'flex flex-none items-center justify-center border border-line-strong bg-raised font-mono font-semibold text-ink-base',
    $classes,
]) }}>
    {{ $initials }}
</span>
