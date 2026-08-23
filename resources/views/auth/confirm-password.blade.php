<x-layouts.guest title="Passwort bestätigen">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-ink">Passwort bestätigen</h1>
        <p class="mt-1 text-sm text-ink-muted">
            Dieser Bereich ist zusätzlich geschützt. Bitte bestätigen Sie Ihr Passwort.
        </p>
    </div>

    <x-errors title="Bestätigung fehlgeschlagen" class="mb-4" />

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-password label="Passwort"
                    name="password"
                    autocomplete="current-password"
                    required
                    autofocus
                    />

        <x-button type="submit" block>Bestätigen</x-button>
    </form>
</x-layouts.guest>
