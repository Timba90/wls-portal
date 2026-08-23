@props(['title', 'subtitle' => null, 'backLabel' => null, 'backUrl' => null])

{{--
    Kopfzeile des Arbeitsbereichs: optionaler Rücksprung, Titel, Untertitel und
    rechts die Aktionen. Entspricht dem 64px hohen Header des Entwurfs, bricht
    auf schmalen Bildschirmen aber um.
--}}
<div class="flex flex-col gap-3 border-b border-line bg-shell px-6 py-3 md:sticky md:top-0 md:z-20 md:h-16 md:flex-row md:items-center md:justify-between md:gap-4 md:py-0">
    <div class="min-w-0">
        @if ($backLabel && $backUrl)
            <a href="{{ $backUrl }}"
               wire:navigate
               class="text-[11px] text-ink-muted transition hover:text-accent">
                {{ $backLabel }}
            </a>
        @endif

        <h1 class="truncate text-[15px] font-semibold text-ink">{{ $title }}</h1>

        @if ($subtitle)
            <p class="truncate text-[11.5px] text-ink-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
