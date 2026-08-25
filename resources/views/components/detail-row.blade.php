@props(['label', 'value' => null])

{{--
    Zeile einer Stammdatenliste. Der Wert kommt entweder als `value` oder,
    wenn er ausgezeichnet werden soll (Pille, Symbol), als Inhalt.
--}}
<div {{ $attributes->class(['grid grid-cols-3 gap-4 py-2 text-[12.5px]']) }}>
    <dt class="text-ink-muted">{{ $label }}</dt>
    <dd class="col-span-2 text-ink-base">
        @if (filled($value))
            {{ $value }}
        @elseif ($slot->isNotEmpty())
            {{ $slot }}
        @else
            —
        @endif
    </dd>
</div>
