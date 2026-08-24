<!DOCTYPE html>
{{--
    Die Oberfläche ist dauerhaft dunkel. Die Klasse steht fest am <html> statt
    über eine Alpine-Bindung: es gibt keine Auswahl mehr, also auch nichts, was
    zur Laufzeit umschalten müsste. Ohne Bindung entfällt zugleich das kurze
    Aufblitzen der hellen Fassung, bevor Alpine startet.
--}}
<html lang="de" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">

    {{-- TallStackUI wird über resources/css/app.css in denselben Tailwind-Build
         kompiliert; <tallstackui:style /> entfällt deshalb bewusst. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire bringt Alpine mit. TallStackUI benoetigt Alpine auch auf Seiten
         ohne Livewire-Komponente, deshalb werden die Assets hier explizit
         eingebunden statt auf die automatische Injektion zu vertrauen. --}}
    @livewireStyles
    @livewireScripts

    <tallstackui:script />
</head>
<body class="h-full bg-canvas font-sans text-ink-base antialiased">
    <div x-data="{ mobileNav: false }" class="flex min-h-full">
        {{-- Seitenleiste: 242px, sticky, eigene Fläche gegenüber dem Arbeitsbereich. --}}
        <aside x-cloak
               x-bind:class="mobileNav ? 'flex' : 'hidden md:flex'"
               class="fixed inset-y-0 left-0 z-40 w-[242px] flex-none flex-col border-r border-line bg-shell md:sticky md:top-0 md:h-screen">
            <div class="flex h-16 flex-none items-center justify-between border-b border-line px-4">
                <a href="{{ route('dashboard') }}" wire:navigate>
                    <x-brand />
                </a>

                <div class="flex items-center gap-1">
                    <button type="button"
                            class="cursor-pointer text-ink-faint transition hover:text-ink md:hidden"
                            x-on:click="mobileNav = false"
                            aria-label="Navigation schließen">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <nav class="flex flex-1 flex-col gap-4 overflow-y-auto px-2 py-3 soft-scrollbar">
                <x-nav-group label="Arbeit">
                    <x-nav-item route="dashboard" icon="squares-2x2" label="Übersicht" />
                    <x-nav-item route="customers.index" match="customers.*" icon="building-office-2"
                                label="Kunden" :count="$navCounts['customers']" />
                    <x-nav-item route="projects.index" match="projects.*" icon="rectangle-group"
                                label="Projekte" />
                    <x-nav-item route="services.index" icon="clipboard-document-list"
                                label="Leistungen" :count="$navCounts['services']" />
                    <x-nav-item route="contacts.index" match="contacts.*" icon="user-group"
                                label="Ansprechpartner" :count="$navCounts['contacts']" />
                    <x-nav-item route="products.index" match="products.*" icon="cube"
                                label="Artikel" :count="$navCounts['products']" />
                </x-nav-group>

                <x-nav-group label="Katalog">
                    <x-nav-item route="categories.index" icon="folder" label="Kategorien" />
                    <x-nav-item route="contact-roles.index" icon="identification" label="Rollen" />
                    <x-nav-item route="project-types.index" icon="rectangle-group" label="Projekttypen" />
                    <x-nav-item route="custom-fields.index" icon="rectangle-stack" label="Eigene Felder" />
                </x-nav-group>

                <x-nav-group label="System">
                    <x-nav-item route="archive.index" icon="archive-box" label="Archiv" />
                    <x-nav-item route="users.index" icon="users" label="Benutzer" />
                    <x-nav-item :href="url('horizon')" icon="queue-list" label="Queue & Jobs" />
                </x-nav-group>
            </nav>

            <div class="flex flex-none items-center gap-2.5 border-t border-line px-3 py-3">
                <x-avatar-initials :initials="auth()->user()->initials()" size="sm" />

                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate text-[11.5px] text-ink-base">{{ auth()->user()->email }}</span>
                    <span class="text-[10px] text-ink-faint">Interner Benutzer</span>
                </span>

                <button type="button"
                        class="cursor-pointer text-[10.5px] text-ink-faint transition hover:text-ink"
                        onclick="document.getElementById('logout-form').submit()">
                    abmelden
                </button>
            </div>
        </aside>

        {{-- Abdunklung hinter der mobilen Navigation. --}}
        <div x-cloak
             x-show="mobileNav"
             x-on:click="mobileNav = false"
             class="fixed inset-0 z-30 bg-black/60 md:hidden"></div>

        <main class="flex min-w-0 flex-1 flex-col">
            <div class="flex items-center gap-3 border-b border-line bg-shell px-4 py-2 md:hidden">
                <button type="button"
                        class="cursor-pointer text-ink-muted transition hover:text-ink"
                        x-on:click="mobileNav = true"
                        aria-label="Navigation öffnen">
                    <x-icon name="bars-3" class="h-5 w-5" />
                </button>

                <x-brand compact />

            </div>

            {{ $slot }}
        </main>
    </div>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    <x-toast />
    <x-dialog />

    {{-- Automatischer Logout nach 30 Minuten Inaktivitaet (siehe SESSION_LIFETIME). --}}
    <div x-data="idleLogout({ minutes: {{ (int) config('session.lifetime') }} })"></div>
</body>
</html>
