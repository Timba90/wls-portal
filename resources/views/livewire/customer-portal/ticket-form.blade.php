<div>
    <x-page title="Anliegen melden"
            subtitle="Beschreiben Sie kurz, worum es geht — wir melden uns schnellstmöglich."
            back-label="Tickets ／ zurück zur Liste"
            :back-url="route('customer-portal.tickets.index')">
        <div class="max-w-[720px]">
            <x-panel>
                <form wire:submit="save" class="flex flex-col gap-4">
                    <x-input label="Betreff" wire:model="subject" placeholder="z. B. Kontaktformular versendet keine E-Mails" required />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select.styled label="Dringlichkeit"
                                         wire:model="priority"
                                         :options="$priorities"
                                         select="label:label|value:value" />

                        <x-select.styled label="Projekt (optional)"
                                         wire:model="projectId"
                                         placeholder="Kein bestimmtes Projekt"
                                         :options="$projects"
                                         select="label:label|value:value" />
                    </div>

                    <x-textarea label="Nachricht"
                                wire:model="body"
                                rows="8"
                                placeholder="Was ist passiert? Seit wann? Auf welcher Seite?"
                                required />

                    <div class="flex gap-2">
                        <x-button type="submit" loading="save">Ticket absenden</x-button>
                        <x-button flat :href="route('customer-portal.tickets.index')" wire:navigate>Abbrechen</x-button>
                    </div>
                </form>
            </x-panel>
        </div>
    </x-page>
</div>
