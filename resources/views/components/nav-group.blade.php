@props(['label'])

{{-- Navigationsgruppe mit Versalien-Label, wie im Entwurf. --}}
<div class="flex flex-col gap-0.5">
    <span class="px-2 pb-1 pt-1.5 text-[9px] font-semibold uppercase tracking-[0.11em] text-ink-faint">
        {{ $label }}
    </span>

    {{ $slot }}
</div>
