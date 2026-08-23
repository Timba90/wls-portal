<x-layouts.guest title="Neues Passwort vergeben">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-ink">Neues Passwort vergeben</h1>
        <p class="mt-1 text-sm text-ink-muted">
            Mindestens 12 Zeichen mit Groß- und Kleinbuchstaben, Zahl und Sonderzeichen.
        </p>
    </div>

    <x-errors title="Passwort konnte nicht gesetzt werden" class="mb-4" />

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input label="E-Mail-Adresse"
                 name="email"
                 type="email"
                 autocomplete="username"
                 required
                 :value="old('email', $request->email)" />

        <x-password label="Neues Passwort"
                    name="password"
                    autocomplete="new-password"
                    :rules="config('portal.password_rules')"
                    required
                    autofocus />

        <x-password label="Neues Passwort bestätigen"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required />

        <x-button type="submit" block>Passwort speichern</x-button>
    </form>
</x-layouts.guest>
