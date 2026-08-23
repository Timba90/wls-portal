<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Überblick über Kunden, Leistungen und Kennzahlen.
        </p>
    </div>

    <x-card>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Willkommen, {{ auth()->user()->name }}.
        </p>
    </x-card>
</div>
