<!DOCTYPE html>
{{--
    Bewusst ohne `tallstackui_darkTheme`: die Anmeldeseite bleibt hell, egal was
    im System oder in der Anwendung eingestellt ist. Ohne die Klasse `dark` am
    <html> lösen alle Tokens — auch die der TallStackUI-Formularfelder — auf
    ihre hellen Werte auf. Das Farbschema der Anwendung bleibt davon unberührt;
    gewählt wird es nach der Anmeldung im Kopf der Oberfläche.
--}}
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @livewireScripts

    <tallstackui:script />
</head>
{{--
    Die Anmeldeseite folgt bewusst nicht dem Farbschema: die Markenspalte bleibt
    dunkel, die Formularseite hell. Im Dark Mode standen sonst zwei fast gleich
    dunkle Hälften nebeneinander. Das Farbschema wählt man in der Anwendung.
--}}
<body class="min-h-full bg-canvas font-sans text-ink-base antialiased">
    {{--
        `flex-wrap-reverse` sorgt dafür, dass auf schmalen Bildschirmen das
        Formular oben steht und die Markenspalte darunter.
    --}}
    <div class="flex min-h-screen w-full flex-wrap-reverse items-stretch">
        <aside class="relative flex flex-[1_1_380px] flex-col justify-between gap-10 overflow-hidden bg-brand-shell px-8 py-9 sm:px-11">
            {{--
                Rein dekoratives Raster hinter dem Inhalt. Nimmt keine
                Zeigerereignisse an und bleibt bei reduzierter Bewegung aus.
            --}}
            <canvas id="marken-raster"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 z-0 h-full w-full"></canvas>

            <div class="relative z-10">
                <x-brand on-brand />
            </div>

            <div class="relative z-10 flex max-w-[430px] flex-col gap-6">
                <h1 class="text-[30px] font-semibold leading-[1.2] tracking-[-0.02em] text-brand-text text-pretty">
                    Kunden, Leistungen und Verträge an einer Stelle.
                </h1>
                <p class="text-[13.5px] leading-relaxed text-brand-muted text-pretty">
                    Betreuung, Abrechnung und Laufzeiten für alle Mandate — ohne Tabellenchaos,
                    mit einer verbindlichen Zahl pro Kunde.
                </p>
            </div>

            @isset($aside)
                <div class="relative z-10">
                    {{ $aside }}
                </div>
            @endisset

            <div class="relative z-10 flex flex-wrap items-center gap-4 text-[10.5px] text-brand-dim">
                <span>© {{ now()->year }} {{ config('portal.brand.name') }}</span>
            </div>
        </aside>

        <main class="flex flex-[1_1_420px] items-center justify-center px-5 py-10 sm:px-10">
            <div class="flex w-full max-w-[400px] flex-col gap-7">
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-toast />
</body>
</html>
