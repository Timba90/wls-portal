@props(['kind' => 'mute', 'label', 'dot' => true])

{{--
    Statuspille aus dem Entwurf.

    `kind` ist eine der fünf Ausprägungen ok, warn, bad, info, mute. Die Farben
    stehen als Tokens in resources/css/app.css und gelten in hell wie dunkel.
--}}
<span {{ $attributes->class(['pill', 'pill-'.$kind]) }}>
    @if ($dot)
        <span class="h-[7px] w-[7px] flex-none rounded-full bg-current opacity-90"></span>
    @endif
    {{ $label }}
</span>
