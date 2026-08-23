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

    {{-- TallStackUI wird ueber resources/css/app.css in denselben Tailwind-Build
         kompiliert; <tallstackui:style /> entfaellt deshalb bewusst. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire bringt Alpine mit. TallStackUI benoetigt Alpine auch auf Seiten
         ohne Livewire-Komponente, deshalb werden die Assets hier explizit
         eingebunden statt auf die automatische Injektion zu vertrauen. --}}
    @livewireStyles
    @livewireScripts

    <tallstackui:script />
</head>
<body class="flex min-h-full flex-col bg-gray-100 font-sans text-gray-900 antialiased dark:bg-dark-900 dark:text-gray-100">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-8 flex flex-col items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-600 text-base font-bold text-white">
                    WLS
                </span>
                <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name') }}</span>
            </div>

            <x-card>
                {{ $slot }}
            </x-card>

            <div class="mt-6 flex justify-center">
                <x-theme-switch sm />
            </div>
        </div>
    </div>

    <x-toast />
</body>
</html>
