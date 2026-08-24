@props(['compact' => false, 'onBrand' => false])

{{--
    Markenblock: Monogramm-Chip plus Wortmarke.

    `on-brand` stellt auf die Marken-Tokens um — nötig überall dort, wo der
    Block auf der konstant dunklen Markenfläche der Anmeldeseite steht und
    deshalb nicht dem Farbschema folgen darf.
--}}
<div {{ $attributes->class(['flex items-center gap-[11px]']) }}>
    <span @class([
        'flex h-7 w-7 flex-none items-center justify-center rounded-[7px] font-mono text-[11px] font-semibold',
        'bg-accent text-accent-ink' => ! $onBrand,
        'bg-brand-accent text-brand-accent-ink' => $onBrand,
    ])>
        wls
    </span>

    @unless ($compact)
        <span class="flex flex-col gap-[2px]">
            <span @class([
                'font-mono text-[12.5px] font-semibold tracking-[0.04em]',
                'text-ink' => ! $onBrand,
                'text-brand-text' => $onBrand,
            ])>
                {{ config('portal.brand.name') }}
            </span>
            <span @class([
                'text-[10px] tracking-[0.03em]',
                'text-ink-faint' => ! $onBrand,
                'text-brand-dim' => $onBrand,
            ])>
                {{ config('portal.brand.tagline') }}
            </span>
        </span>
    @endunless
</div>
