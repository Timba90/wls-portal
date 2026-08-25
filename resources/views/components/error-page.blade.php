@props([
    'code',
    'title',
    'message',
    'actionLabel' => null,
    'actionUrl' => null,
])

{{--
    Gerüst der Fehlerseiten.

    Bewusst ohne Seitenleiste und ohne Livewire: eine Fehlerseite muss auch
    dann stehen, wenn die Sitzung abgelaufen oder etwas kaputt ist. Sie folgt
    dem dunklen Schema der Anwendung, damit sie nicht wie eine fremde Seite
    wirkt — und sie bietet immer einen Weg zurück.
--}}
@php
    $angemeldet = auth()->check();

    $ziel = $actionUrl ?? ($angemeldet ? route('dashboard') : route('login'));
    $beschriftung = $actionLabel ?? ($angemeldet ? 'Zur Übersicht' : 'Zur Anmeldung');
@endphp

<!DOCTYPE html>
<html lang="de" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $code }} · {{ $title }} · {{ config('app.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-canvas font-sans text-ink-base antialiased">
    <main class="flex min-h-screen flex-col items-center justify-center gap-8 px-5 py-12">
        <x-brand />

        <div class="flex w-full max-w-[440px] flex-col items-center gap-5 rounded-[10px] border border-line bg-panel px-6 py-9 text-center">
            <span class="font-mono text-[42px] font-semibold leading-none tracking-[-0.02em] text-ink-faint">
                {{ $code }}
            </span>

            <div class="flex flex-col gap-2">
                <h1 class="text-[15px] font-semibold tracking-[-0.01em] text-ink">{{ $title }}</h1>
                <p class="text-[12.5px] leading-relaxed text-ink-muted">{{ $message }}</p>
            </div>

            <a href="{{ $ziel }}"
               class="mt-1 inline-flex items-center justify-center rounded-[7px] bg-accent px-4 py-2 text-[12.5px] font-medium text-accent-ink transition hover:bg-accent-hover">
                {{ $beschriftung }}
            </a>
        </div>
    </main>
</body>
</html>
