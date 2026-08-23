<x-layouts.guest title="Anmelden">
    <x-slot:title>Anmelden</x-slot:title>

    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Anmelden</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Interne Verwaltungskonsole. Zugang nur für Mitarbeiter.
        </p>
    </div>

    @if (session('status'))
        <x-alert color="green" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if (request()->has('abgelaufen'))
        <x-alert color="amber" class="mb-4">
            Ihre Sitzung wurde wegen Inaktivität beendet. Bitte melden Sie sich erneut an.
        </x-alert>
    @endif

    <x-errors title="Anmeldung nicht möglich" class="mb-4" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-input label="E-Mail-Adresse"
                 name="email"
                 type="email"
                 autocomplete="username"
                 required
                 autofocus
                 :value="old('email')" />

        <x-password label="Passwort"
                    name="password"
                    autocomplete="current-password"
                    required />

        <div class="flex items-center justify-between">
            <x-toggle label="Angemeldet bleiben" name="remember" value="1" sm />

            <a href="{{ route('password.request') }}"
               class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                Passwort vergessen?
            </a>
        </div>

        <x-button type="submit" block>Anmelden</x-button>
    </form>
</x-layouts.guest>
