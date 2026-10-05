<div>
    <x-page title="Sachkonten" subtitle="SKR04 · Sachkonten und Flux-Zuordnungen">
        <div class="flex flex-col gap-4">
            @if (! $configured)
                <p>Bitte zuerst den <a class="underline" href="{{ route('integrations.index') }}">Flux-Zugang einrichten</a>.</p>
            @else
                <x-button wire:click="sync" wire:loading.attr="disabled">Bestand aus Flux abgleichen</x-button>
                <x-card>
                    <form wire:submit="create" class="grid gap-4 md:grid-cols-2">
                        <x-input label="Kontonummer (SKR04)" wire:model="input.number" />
                        <x-input label="Bezeichnung" wire:model="input.name" />
                        <label>Kontotyp
                            <select wire:model="input.type" class="block w-full rounded border border-line bg-raised p-2">
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label><input type="checkbox" wire:model="input.is_automatic"> Automatikkonto</label>
                        <x-button type="submit" wire:loading.attr="disabled">Konto in Flux anlegen / zuordnen</x-button>
                        @foreach ($errors->all() as $error)<p class="text-red-600">{{ $error }}</p>@endforeach
                    </form>
                </x-card>
            @endif
            @if ($message)<p role="status">{{ $message }}</p>@endif
            <x-input label="Sachkonten suchen" wire:model.live.debounce.300ms="search" />
            <x-card>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th>Nummer</th><th>Bezeichnung</th><th>Typ</th><th>Automatik</th><th>Flux-ID</th></tr></thead>
                        <tbody>
                            @forelse ($accounts as $account)
                                <tr wire:key="konto-{{ $account->id }}" class="border-t border-line">
                                    <td class="py-3">{{ $account->number }}</td><td>{{ $account->name }}</td>
                                    <td>{{ \App\Enums\FluxLedgerAccountType::from($account->type)->label() }}</td>
                                    <td>{{ $account->is_automatic ? 'Ja' : 'Nein' }}</td><td>{{ $account->flux_id }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-3">Noch keine Sachkonten abgeglichen.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </x-page>
</div>
