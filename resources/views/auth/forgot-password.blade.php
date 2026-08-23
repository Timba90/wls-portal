<x-layouts.guest title="Passwort vergessen">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Passwort vergessen</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Wir senden Ihnen einen Link, mit dem Sie ein neues Passwort vergeben können.
        </p>
    </div>

    @if (session('status'))
        <x-alert color="green" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <x-errors title="Anfrage nicht möglich" class="mb-4" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-input label="E-Mail-Adresse"
                 name="email"
                 type="email"
                 autocomplete="username"
                 required
                 autofocus
                 :value="old('email')" />

        <x-button type="submit" block>Link anfordern</x-button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                Zurück zur Anmeldung
            </a>
        </div>
    </form>
</x-layouts.guest>
