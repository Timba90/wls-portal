<!DOCTYPE html>
{{--
    Layout des Kundenportals.

    Dieselbe Gestaltung wie die interne Verwaltung — Seitenleiste, Flächen,
    Tokens und Schriften —, aber mit eigener Navigation und dem angemeldeten
    Kundenzugang statt eines internen Benutzers. Wie dort dauerhaft dunkel.
--}}
@php($portalUser = auth('customer')->user())
<html lang="de" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · '.config('portal.customer_portal.tagline') : config('portal.customer_portal.tagline') }} · {{ config('portal.brand.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @livewireScripts

    <tallstackui:script />
</head>
<body class="h-full bg-canvas font-sans text-ink-base antialiased">
    <div x-data="{ mobileNav: false }" class="flex min-h-full">
        <aside x-cloak
               x-bind:class="mobileNav ? 'flex' : 'hidden md:flex'"
               class="fixed inset-y-0 left-0 z-40 w-[242px] flex-none flex-col border-r border-line bg-shell md:sticky md:top-0 md:h-screen">
            <div class="flex h-16 flex-none items-center justify-between border-b border-line px-4">
                <a href="{{ route('customer-portal.dashboard') }}" wire:navigate>
                    <x-brand :tagline="config('portal.customer_portal.tagline')" />
                </a>

                <button type="button"
                        class="cursor-pointer text-ink-faint transition hover:text-ink md:hidden"
                        x-on:click="mobileNav = false"
                        aria-label="Navigation schließen">
                    <x-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex flex-1 flex-col gap-4 overflow-y-auto px-2 py-3 soft-scrollbar">
                <x-nav-group label="Übersicht">
                    <x-nav-item route="customer-portal.dashboard" icon="squares-2x2" label="Start" />
                    <x-nav-item route="customer-portal.projects.index" match="customer-portal.projects.*"
                                icon="rectangle-group" label="Projekte" />
                    <x-nav-item route="customer-portal.tickets.index" match="customer-portal.tickets.*"
                                icon="chat-bubble-left-right" label="Tickets"
                                :count="$portalNavCounts['tickets'] ?? null" />
                </x-nav-group>

                <x-nav-group label="Abrechnung">
                    <x-nav-item route="customer-portal.invoices.index" icon="document-text" label="Rechnungen" />
                    <x-nav-item route="customer-portal.sepa-mandate" icon="building-library" label="SEPA-Mandat" />
                </x-nav-group>

                <x-nav-group label="Konto">
                    <x-nav-item route="customer-portal.profile" icon="user-circle" label="Profil & Passwort" />
                </x-nav-group>
            </nav>

            <div class="flex flex-none flex-col gap-2 border-t border-line px-3 py-3">
                <span class="truncate text-[10px] font-semibold uppercase tracking-[0.11em] text-ink-faint">
                    {{ $portalUser->customer->displayName() }}
                </span>

                <div class="flex items-center gap-2.5">
                    <x-avatar-initials :initials="$portalUser->initials()" size="sm" />

                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate text-[11.5px] text-ink-base">{{ $portalUser->email }}</span>
                        <span class="font-mono text-[10px] text-ink-faint">{{ $portalUser->customer->customer_number }}</span>
                    </span>

                    <button type="button"
                            class="cursor-pointer text-[10.5px] text-ink-faint transition hover:text-ink"
                            onclick="document.getElementById('portal-logout-form').submit()">
                        abmelden
                    </button>
                </div>
            </div>
        </aside>

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

    <form id="portal-logout-form" method="POST" action="{{ route('customer-portal.logout') }}" class="hidden">
        @csrf
    </form>

    <x-toast />
    <x-dialog />

    <div x-data="idleLogout({ minutes: {{ (int) config('session.lifetime') }}, redirect: @js(route('customer-portal.login', ['abgelaufen' => 1])) })"></div>
</body>
</html>
