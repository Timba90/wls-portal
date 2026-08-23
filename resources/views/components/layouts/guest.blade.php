<!DOCTYPE html>
<html lang="de"
      x-data="tallstackui_darkTheme({ default: 'system' })"
      x-bind:class="{ 'dark': darkTheme }"
      class="h-full">
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
<body class="min-h-full bg-canvas font-sans text-ink-base antialiased">
    {{--
        Zweispaltig wie im Entwurf. `flex-wrap-reverse` sorgt dafür, dass auf
        schmalen Bildschirmen das Formular oben steht und die Markenspalte
        darunter.
    --}}
    <div class="flex min-h-screen w-full flex-wrap-reverse items-stretch">
        <aside class="flex flex-[1_1_380px] flex-col justify-between gap-10 border-r border-line bg-shell px-8 py-9 sm:px-11">
            <x-brand />

            <div class="flex max-w-[430px] flex-col gap-6">
                <h1 class="text-[30px] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">
                    Kunden, Leistungen und Verträge an einer Stelle.
                </h1>
                <p class="text-[13.5px] leading-relaxed text-ink-muted">
                    Betreuung, Abrechnung und Laufzeiten für alle Mandate — ohne Tabellenchaos,
                    mit einer verbindlichen Zahl pro Kunde.
                </p>
            </div>

            @isset($aside)
                {{ $aside }}
            @endisset

            <div class="flex flex-wrap items-center gap-4 text-[10.5px] text-ink-faint">
                <span>© {{ now()->year }} {{ config('portal.brand.name') }}</span>
                <x-theme-switch xs class="ml-auto" />
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
