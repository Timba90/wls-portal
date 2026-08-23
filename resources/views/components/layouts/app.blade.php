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
<body class="bg-gray-50 font-sans text-gray-900 antialiased dark:bg-dark-900 dark:text-gray-100 h-full">
    <x-layout>
        <x-slot:menu>
            <x-side-bar collapsible navigate>
                <x-slot:brand>
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 px-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-600 text-sm font-bold text-white">
                            WLS
                        </span>
                        <span class="whitespace-nowrap text-sm font-semibold text-gray-800 dark:text-gray-100">Portal</span>
                    </a>
                </x-slot:brand>

                <x-slot:brandCollapsed>
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center px-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-600 text-sm font-bold text-white">
                            WLS
                        </span>
                    </a>
                </x-slot:brandCollapsed>

                <x-side-bar.item text="Dashboard" icon="home" route="dashboard" />

                <x-side-bar.item text="Kunden" icon="building-office-2" route="customers.index" match="customers.*" />

                <x-side-bar.item text="Ansprechpartner" icon="user-group" route="contacts.index" match="contacts.*" />

                <x-side-bar.item text="Artikel / Leistungen" icon="squares-2x2" route="products.index" match="products.*" />

                <x-side-bar.separator text="Verwaltung" />

                <x-side-bar.item text="Kategorien" icon="folder" route="categories.index" />
                <x-side-bar.item text="Tags" icon="tag" route="tags.index" />
                <x-side-bar.item text="Ansprechpartnerrollen" icon="identification" route="contact-roles.index" />
                <x-side-bar.item text="Benutzer" icon="users" route="users.index" />
                <x-side-bar.item text="Warteschlangen" icon="queue-list" href="{{ url('horizon') }}" />
            </x-side-bar>
        </x-slot:menu>

        <x-slot:header>
            <x-layout.header>
                <x-slot:right>
                    <div class="flex items-center gap-3">
                        <x-theme-switch sm />

                        <x-dropdown static>
                            <x-slot:action>
                                <button type="button"
                                        class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-dark-600">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-100">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                    <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                                </button>
                            </x-slot:action>

                            <x-dropdown.items text="Profil" icon="user" :href="route('profile.show')" />
                            <x-dropdown.items text="Sicherheit" icon="shield-check" :href="route('profile.security')" />
                            <x-dropdown.items separator />
                            <x-dropdown.items text="Abmelden" icon="arrow-right-start-on-rectangle" onclick="document.getElementById('logout-form').submit()" />
                        </x-dropdown>
                    </div>
                </x-slot:right>
            </x-layout.header>
        </x-slot:header>

        @isset($header)
            <div class="mb-6">{{ $header }}</div>
        @endisset

        {{ $slot }}
    </x-layout>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    <x-toast />
    <x-dialog />

    {{-- Automatischer Logout nach 30 Minuten Inaktivitaet (siehe SESSION_LIFETIME). --}}
    <div x-data="idleLogout({ minutes: {{ (int) config('session.lifetime') }} })"></div>
</body>
</html>
