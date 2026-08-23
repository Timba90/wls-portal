@props(['compact' => false])

{{-- Markenblock aus dem Entwurf: Monogramm-Chip plus Wortmarke. --}}
<div {{ $attributes->class(['flex items-center gap-[11px]']) }}>
    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-[7px] bg-accent font-mono text-[11px] font-semibold text-accent-ink">
        wls
    </span>

    @unless ($compact)
        <span class="flex flex-col gap-[2px]">
            <span class="font-mono text-[12.5px] font-semibold tracking-[0.04em] text-ink">
                {{ config('portal.brand.name') }}
            </span>
            <span class="text-[10px] tracking-[0.03em] text-ink-faint">
                {{ config('portal.brand.tagline') }}
            </span>
        </span>
    @endunless
</div>
