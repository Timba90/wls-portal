<?php

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Der zweite Weg in den MCP-Server.
 *
 * Geprüft wird der ganze Fluss — Zustimmung, Code, Token, Aufruf — und die
 * beiden Absperrungen: Clients legt nur jemand von Hand an, und ein Zugriff
 * ohne den Geltungsbereich `mcp:use` kommt nicht durch.
 */
beforeEach(function (): void {
    $this->benutzer = User::factory()->create();

    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Testclient',
        ['https://client.test/rueckruf'],
        confidential: false,
    );
});

/**
 * Ein Aufruf des MCP-Servers, wie ein Client ihn stellt.
 */
function werkzeugliste(string $token): TestResponse
{
    return test()->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('mcp.portal'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);
}

it('nennt im Auffinde-Dokument die Endpunkte, aber keine Selbstregistrierung', function (): void {
    $antwort = $this->getJson('/.well-known/oauth-authorization-server')->assertOk();

    expect($antwort->json('authorization_endpoint'))->toBe(route('passport.authorizations.authorize'))
        ->and($antwort->json('token_endpoint'))->toBe(route('passport.token'))
        ->and($antwort->json('scopes_supported'))->toBe([Registrar::OAUTH_SCOPE])
        ->and($antwort->json('code_challenge_methods_supported'))->toBe(['S256'])
        // Ohne dieses Feld fragt ein Client nach Zugangsdaten, statt sich
        // selbst anzumelden — genau das ist hier gewollt.
        ->and($antwort->json())->not->toHaveKey('registration_endpoint');
});

it('weist den geschuetzten Bestand samt Geltungsbereich aus', function (): void {
    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonPath('scopes_supported', [Registrar::OAUTH_SCOPE]);
});

it('hat keinen offenen Endpunkt zum Anlegen von Clients', function (): void {
    // Die Selbstregistrierung nach RFC 7591 waere die einzige Seite ohne
    // Anmeldung neben Login und Passwort-Ruecksetzung.
    $this->postJson('/oauth/register', ['client_name' => 'Fremder'])->assertNotFound();
});

it('bietet keinen Device-Grant an', function (): void {
    // Dessen Endpunkt waere ebenfalls ohne Anmeldung erreichbar.
    $this->postJson('/oauth/device/code', ['client_id' => $this->client->getKey()])->assertNotFound();
});

it('verlangt vor der Zustimmung eine Anmeldung', function (): void {
    // Vollstaendig genug, dass die Pruefung der Anfrage durchlaeuft — sonst
    // bricht Passport schon davor ab und man misst den falschen Fall.
    $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->client->getKey(),
        'redirect_uri' => 'https://client.test/rueckruf',
        'response_type' => 'code',
        'scope' => Registrar::OAUTH_SCOPE,
        'code_challenge' => Str::random(43),
        'code_challenge_method' => 'S256',
    ]))->assertRedirect(route('login'));
});

it('fuehrt vom Zustimmen bis zum Aufruf des Servers', function (): void {
    $pruefer = Str::random(64);
    $herausforderung = rtrim(strtr(base64_encode(hash('sha256', $pruefer, true)), '+/', '-_'), '=');

    // 1. Der Benutzer sieht, worum gebeten wird.
    $this->actingAs($this->benutzer)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $this->client->getKey(),
            'redirect_uri' => 'https://client.test/rueckruf',
            'response_type' => 'code',
            'scope' => Registrar::OAUTH_SCOPE,
            'state' => 'zustand',
            'code_challenge' => $herausforderung,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Zugriff erlauben?')
        ->assertSee('Testclient')
        ->assertSee('Zugriff auf den MCP-Server im eigenen Namen');

    // 2. Er stimmt zu.
    $weiterleitung = $this->actingAs($this->benutzer)
        ->post('/oauth/authorize', [
            'state' => 'zustand',
            'client_id' => $this->client->getKey(),
            'auth_token' => session('authToken'),
        ])
        ->assertRedirect()
        ->headers->get('Location');

    parse_str(parse_url((string) $weiterleitung, PHP_URL_QUERY) ?: '', $rueckgabe);

    expect($rueckgabe['state'] ?? null)->toBe('zustand')
        ->and($rueckgabe['code'] ?? null)->not->toBeEmpty();

    // 3. Der Client tauscht den Code gegen ein Token.
    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->getKey(),
        'redirect_uri' => 'https://client.test/rueckruf',
        'code_verifier' => $pruefer,
        'code' => $rueckgabe['code'],
    ])->assertOk();

    expect($token->json('token_type'))->toBe('Bearer')
        ->and($token->json('refresh_token'))->not->toBeEmpty();

    // 4. Und ruft damit den Server auf.
    werkzeugliste($token->json('access_token'))
        ->assertOk()
        ->assertJsonPath('result.tools.0.name', 'kunden-suchen');
});

it('weist ein Zugriffstoken ohne den Geltungsbereich ab', function (): void {
    Passport::actingAs($this->benutzer, []);

    $this->postJson(route('mcp.portal'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
});

it('laesst ein Zugriffstoken mit dem Geltungsbereich durch', function (): void {
    Passport::actingAs($this->benutzer, [Registrar::OAUTH_SCOPE]);

    $this->postJson(route('mcp.portal'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk();
});

it('laesst die persoenlichen Tokens weiter zu', function (): void {
    // Der erste Weg soll vom zweiten unberührt bleiben.
    $token = $this->benutzer->createToken('Arbeitsplatz')->plainTextToken;

    werkzeugliste($token)->assertOk();
});

it('weist einen Aufruf ohne jede Anmeldung ab', function (): void {
    $this->postJson(route('mcp.portal'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

it('probiert die Guards in der Reihenfolge, die beide Wege offen laesst', function (): void {
    // `auth:api,sanctum` liess persoenliche Tokens ins Leere laufen: der
    // OAuth-Guard beantwortet den Versuch als erster und die Kette bricht ab.
    // Die Reihenfolge ist deshalb keine Geschmacksfrage.
    $middleware = collect(app('router')->getRoutes()->getByName('mcp.portal')->gatherMiddleware());

    expect($middleware)->toContain('auth:sanctum,api');
});

it('haelt die Zustimmung hinter der Anmeldung, den Token-Endpunkt aber offen', function (): void {
    // Der Token-Endpunkt muss offen sein: dort weist sich der Client selbst
    // aus, nicht der Benutzer. Er gibt ohne gueltigen Code nichts heraus.
    $this->postJson('/oauth/token', ['grant_type' => 'authorization_code'])
        ->assertStatus(400);

    // Die Zustimmung dagegen gehoert hinter die Anmeldung — siehe oben.
    // Welcher Guard genau, haengt an der Konfiguration von Passport — dass
    // ueberhaupt einer davorsteht, ist der Punkt.
    $middleware = collect(app('router')->getRoutes()->getByName('passport.authorizations.approve')->gatherMiddleware());

    expect($middleware->filter(fn (string $eintrag): bool => str_starts_with($eintrag, 'auth')))->not->toBeEmpty();
});

it('beantwortet die Auffinde-Dokumente auch mit Pfadteil', function (): void {
    // RFC 8414 erlaubt `/.well-known/oauth-authorization-server/<pfad>`. Beim
    // geschuetzten Bestand gehoert der Pfad in die Antwort, beim
    // Autorisierungsserver aendert er nichts — beides muss antworten, statt
    // an der Signatur des Closures zu scheitern.
    $this->getJson('/.well-known/oauth-authorization-server/'.config('portal.mcp.path'))
        ->assertOk()
        ->assertJsonPath('token_endpoint', route('passport.token'));

    $this->getJson('/.well-known/oauth-protected-resource/'.config('portal.mcp.path'))
        ->assertOk()
        ->assertJsonPath('resource', url('/'.config('portal.mcp.path')));
});
