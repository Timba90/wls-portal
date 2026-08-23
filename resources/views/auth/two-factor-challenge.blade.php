<x-layouts.guest title="Zwei-Faktor-Bestätigung">
    <div x-data="{ recovery: false }">
        <div class="mb-6">
            <h1 class="text-lg font-semibold text-ink">Zwei-Faktor-Bestätigung</h1>
            <p class="mt-1 text-sm text-ink-muted" x-show="!recovery">
                Bitte geben Sie den Code aus Ihrer Authenticator-App ein.
            </p>
            <p class="mt-1 text-sm text-ink-muted" x-show="recovery" x-cloak>
                Bitte geben Sie einen Ihrer Wiederherstellungscodes ein.
            </p>
        </div>

        <x-errors title="Bestätigung fehlgeschlagen" class="mb-4" />

        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
            @csrf

            <div x-show="!recovery">
                <x-input label="Code"
                         name="code"
                         inputmode="numeric"
                         autocomplete="one-time-code"
                         autofocus />
            </div>

            <div x-show="recovery" x-cloak>
                <x-input label="Wiederherstellungscode"
                         name="recovery_code"
                         autocomplete="one-time-code" />
            </div>

            <x-button type="submit" block>Bestätigen</x-button>

            <div class="text-center">
                <button type="button"
                        x-on:click="recovery = !recovery"
                        class="cursor-pointer text-sm text-accent hover:underline">
                    <span x-show="!recovery">Stattdessen Wiederherstellungscode verwenden</span>
                    <span x-show="recovery" x-cloak>Stattdessen Authenticator-Code verwenden</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.guest>
