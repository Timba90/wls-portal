{{--
    Zustimmung zu einem OAuth-Zugriff.

    Der Benutzer ist an dieser Stelle bereits angemeldet. Zu entscheiden ist
    nur noch, ob dieser Client in seinem Namen auf den MCP-Server zugreifen
    darf. Deshalb steht hier, was der Zugriff tatsächlich bedeutet — nicht nur
    der Name des Geltungsbereichs.
--}}
<x-layouts.guest title="Zugriff erlauben">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-ink">Zugriff erlauben?</h1>
        <p class="mt-1 text-sm text-ink-muted">
            <span class="font-medium text-ink-base">{{ $client->name }}</span>
            möchte im Namen von
            <span class="font-medium text-ink-base">{{ $user->name }}</span>
            auf das Portal zugreifen.
        </p>
    </div>

    <div class="mb-5 rounded-[10px] border border-line bg-raised px-4 py-3">
        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Umfang</p>

        <ul class="mt-2 space-y-1.5">
            @foreach ($scopes as $scope)
                <li class="flex gap-2 text-[12.5px] text-ink-base">
                    <span class="text-accent">•</span>
                    <span>{{ $scope->description }}</span>
                </li>
            @endforeach
        </ul>

        <p class="mt-3 border-t border-line pt-2.5 text-[12px] leading-relaxed text-ink-muted">
            Der Zugriff gilt mit Ihren Rechten. Jede Änderung erscheint unter Ihrem Namen in der
            Änderungshistorie. Sie können die Erlaubnis jederzeit widerrufen.
        </p>
    </div>

    <div class="flex gap-2">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <x-button type="submit" block>Erlauben</x-button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <x-button type="submit" block color="secondary" outline>Ablehnen</x-button>
        </form>
    </div>
</x-layouts.guest>
