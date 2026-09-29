<?php

use App\Actions\Oauth\ResolveClientFromMetadataDocument;
use App\Models\OauthClientDocument;
use App\Models\User;
use App\Support\Oauth\PublicHostGuard;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

/*
 * Ein Client weist sich aus, indem er unter einer HTTPS-Adresse ein Dokument
 * veröffentlicht und genau diese Adresse als `client_id` schickt. Geprüft wird
 * hier beides: dass ein gültiges Dokument bis zu einem brauchbaren Token
 * durchkommt, und dass jede der Regeln aus der Spezifikation wirklich abweist.
 */

const KENNUNG = 'https://chatgpt.com/connector_platform_oauth_client.json';
const RUECKLEITUNG = 'https://chatgpt.com/connector_platform_oauth_redirect';

beforeEach(function (): void {
    // Die Namensauflösung gehört nicht in einen Test; geprüft wird sie
    // getrennt über PublicHostGuard::isPublic().
    $this->app->bind(PublicHostGuard::class, fn (): PublicHostGuard => new class extends PublicHostGuard
    {
        protected function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

function dokument(array $ueberschreiben = []): array
{
    return array_merge([
        'client_id' => KENNUNG,
        'client_name' => 'ChatGPT',
        'token_endpoint_auth_method' => 'none',
        'redirect_uris' => [RUECKLEITUNG],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
    ], $ueberschreiben);
}

function antwortMit(array $dokument, int $status = 200): void
{
    Http::fake([KENNUNG => Http::response($dokument, $status)]);
}

it('nennt die Unterstützung im Entdeckungsdokument', function (): void {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('client_id_metadata_document_supported', true)
        ->assertJsonPath('token_endpoint_auth_methods_supported', ['none'])
        // Ein Registrierungsendpunkt bleibt es ausdrücklich nicht.
        ->assertJsonMissingPath('registration_endpoint');
});

it('legt aus einem gültigen Dokument einen öffentlichen Client an', function (): void {
    antwortMit(dokument());

    $client = app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    expect($client)->not->toBeNull()
        ->and($client->name)->toBe('ChatGPT')
        ->and($client->secret)->toBeNull()
        ->and($client->confidential())->toBeFalse()
        ->and($client->redirect_uris)->toBe([RUECKLEITUNG]);

    expect(OauthClientDocument::query()->where('url', KENNUNG)->exists())->toBeTrue();
});

it('trifft bei jedem Mal denselben Datensatz', function (): void {
    antwortMit(dokument());

    $erst = app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    OauthClientDocument::query()->update(['refresh_after' => now()->subMinute()]);

    $dann = app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    // Sonst wäre die einmal gegebene Zustimmung beim nächsten Mal wertlos.
    expect($dann->getKey())->toBe($erst->getKey())
        ->and(Client::query()->count())->toBe(1);
});

it('holt das Dokument innerhalb der Frist nicht erneut', function (): void {
    antwortMit(dokument());

    app(ResolveClientFromMetadataDocument::class)(KENNUNG);
    app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    Http::assertSentCount(1);
});

it('führt den ganzen Fluss bis zu einem Token, das am MCP-Server gilt', function (): void {
    antwortMit(dokument());

    $benutzer = User::factory()->create();
    $pruefer = 'x'.str_repeat('a', 63);
    $frage = rtrim(strtr(base64_encode(hash('sha256', $pruefer, true)), '+/', '-_'), '=');

    $this->actingAs($benutzer)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => KENNUNG,
            'redirect_uri' => RUECKLEITUNG,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'zustand',
            'code_challenge' => $frage,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('ChatGPT');

    $weiterleitung = $this->actingAs($benutzer)
        ->post('/oauth/authorize', ['auth_token' => session('authToken'), 'state' => 'zustand'])
        ->assertRedirect()
        ->headers->get('Location');

    parse_str(parse_url($weiterleitung, PHP_URL_QUERY), $rueckgabe);

    expect($rueckgabe)->toHaveKey('code');

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => KENNUNG,
        'redirect_uri' => RUECKLEITUNG,
        'code_verifier' => $pruefer,
        'code' => $rueckgabe['code'],
    ])->assertOk()->json();

    expect($token)->toHaveKeys(['access_token', 'refresh_token']);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token['access_token'],
        'Accept' => 'application/json, text/event-stream',
    ])->postJson(config('portal.mcp.path'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk();
});

it('weist ein Dokument ab, das eine andere Kennung nennt', function (): void {
    antwortMit(dokument(['client_id' => 'https://chatgpt.com/etwas-anderes.json']));

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('weist Clients mit Geheimnis ab', function (): void {
    antwortMit(dokument(['token_endpoint_auth_method' => 'client_secret_basic']));

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('weist ein Dokument ohne Angabe des Anmeldeverfahrens ab', function (): void {
    $ohne = dokument();
    unset($ohne['token_endpoint_auth_method']);
    antwortMit($ohne);

    // Ohne Angabe gilt nach der Registrierungs-Spezifikation
    // client_secret_basic — also ein Geheimnis, das es hier nicht gibt.
    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('weist ein Dokument ohne Autorisierungscode-Fluss ab', function (): void {
    antwortMit(dokument(['grant_types' => ['client_credentials']]));

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('weist Rückleitungen ohne HTTPS ab', function (): void {
    antwortMit(dokument(['redirect_uris' => ['http://chatgpt.com/rueckruf']]));

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('weist alles ab, was nicht mit 200 antwortet', function (): void {
    antwortMit(dokument(), 404);

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('weist ein zu großes Dokument ab', function (): void {
    antwortMit(dokument(['client_name' => str_repeat('x', 6000)]));

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('schaltet einen widerrufenen Client nicht wieder frei', function (): void {
    $stoerung = false;
    Http::fake(function () use (&$stoerung) {
        return $stoerung ? Http::response('', 503) : Http::response(dokument());
    });

    $client = app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    // Von Hand widerrufen, etwa weil der Client auffällig geworden ist.
    $client->forceFill(['revoked' => true])->save();

    OauthClientDocument::query()->update(['refresh_after' => now()->subMinute()]);

    // Ohne die Prüfung hätte der Widerruf eine Halbwertszeit von einem Tag:
    // das nächste Holen des Dokuments würde ihn aufheben.
    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull()
        ->and($client->fresh()->revoked)->toBeTrue();
});

it('weist Kennungen ohne Pfad ab', function (): void {
    Http::fake();

    expect(app(ResolveClientFromMetadataDocument::class)('https://chatgpt.com'))->toBeNull()
        ->and(app(ResolveClientFromMetadataDocument::class)('https://chatgpt.com/'))->toBeNull();

    Http::assertNothingSent();
});

it('holt gar nichts von einem Host, der nicht in der Liste steht', function (): void {
    Http::fake();

    expect(app(ResolveClientFromMetadataDocument::class)('https://fremder.example/client.json'))->toBeNull();

    Http::assertNothingSent();
});

it('überbrückt eine Störung beim Client mit dem letzten geprüften Stand', function (): void {
    // Http::fake() hängt an, statt zu ersetzen — ein zweites fake() für die
    // Störung bliebe wirkungslos. Deshalb ein Fake, der umschaltbar ist.
    $stoerung = false;
    // Als Referenz, sonst fängt die Funktion den Wert beim Definieren ein.
    Http::fake(function () use (&$stoerung) {
        return $stoerung ? Http::response('', 503) : Http::response(dokument());
    });

    $client = app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    $stoerung = true;
    OauthClientDocument::query()->update(['refresh_after' => now()->subMinute()]);

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG)?->getKey())->toBe($client->getKey());
});

it('lässt die Nachfrist enden', function (): void {
    $stoerung = false;
    // Als Referenz, sonst fängt die Funktion den Wert beim Definieren ein.
    Http::fake(function () use (&$stoerung) {
        return $stoerung ? Http::response('', 503) : Http::response(dokument());
    });

    app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    $stoerung = true;
    OauthClientDocument::query()->update([
        'refresh_after' => now()->subMinute(),
        'fetched_at' => now()->subDays((int) config('portal.mcp.oauth.client_documents.grace_days') + 1),
    ]);

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();
});

it('lehnt ab, wenn die Einstellung aus ist', function (): void {
    config(['portal.mcp.oauth.client_documents.enabled' => false]);
    Http::fake();

    expect(app(ResolveClientFromMetadataDocument::class)(KENNUNG))->toBeNull();

    Http::assertNothingSent();
});

it('hält Adressen im eigenen Netz fern', function (): void {
    expect(PublicHostGuard::isPublic('127.0.0.1'))->toBeFalse()
        ->and(PublicHostGuard::isPublic('10.0.0.5'))->toBeFalse()
        ->and(PublicHostGuard::isPublic('192.168.1.1'))->toBeFalse()
        ->and(PublicHostGuard::isPublic('169.254.169.254'))->toBeFalse()
        ->and(PublicHostGuard::isPublic('::1'))->toBeFalse()
        ->and(PublicHostGuard::isPublic('fd00::1'))->toBeFalse()
        ->and(PublicHostGuard::isPublic('93.184.216.34'))->toBeTrue();
});

/*
 * Native Clients.
 *
 * Codex läuft auf dem Rechner des Benutzers und nimmt den Code über einen kurz
 * geöffneten lokalen Port entgegen. Seine Rückleitungen sind deshalb `http`
 * auf die Loopback-Adresse — für native Anwendungen die Regel (RFC 8252,
 * Abschnitt 7.3). Eine Prüfung „nur HTTPS" hat jeden solchen Client abgewiesen,
 * obwohl sein Dokument in Ordnung war.
 *
 * Das Dokument unten ist das, was `chatgpt.com` tatsächlich ausliefert.
 */
const CODEX_KENNUNG = 'https://chatgpt.com/oauth/codex/RfgAVj22ag3i/client.json';

function codexDokument(array $ueberschreiben = []): array
{
    return array_merge([
        'client_id' => CODEX_KENNUNG,
        'client_uri' => 'https://chatgpt.com/codex',
        'application_type' => 'native',
        'redirect_uris' => [
            'http://127.0.0.1/callback/RfgAVj22ag3i',
            'http://localhost/callback/RfgAVj22ag3i',
        ],
        'token_endpoint_auth_method' => 'none',
        'token_endpoint_auth_methods_supported' => ['none'],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'client_name' => 'Codex',
        'logo_uri' => 'https://persistent.oaistatic.com/sonic/misc/openai-logo.png',
    ], $ueberschreiben);
}

it('nimmt ein natives Client-Dokument mit Loopback-Rückleitungen an', function (): void {
    Http::fake([CODEX_KENNUNG => Http::response(codexDokument())]);

    $client = app(ResolveClientFromMetadataDocument::class)(CODEX_KENNUNG);

    expect($client)->toBeInstanceOf(Client::class)
        ->and($client->name)->toBe('Codex')
        ->and($client->redirect_uris)->toBe([
            'http://127.0.0.1/callback/RfgAVj22ag3i',
            'http://localhost/callback/RfgAVj22ag3i',
        ]);
});

it('erlaubt http nur für den eigenen Rechner, nicht für Namen, die so anfangen', function (string $rueckleitung): void {
    Http::fake([CODEX_KENNUNG => Http::response(codexDokument(['redirect_uris' => [$rueckleitung]]))]);

    expect(app(ResolveClientFromMetadataDocument::class)(CODEX_KENNUNG))->toBeNull();
})->with([
    'getarnt als Loopback' => 'http://127.0.0.1.example.com/callback',
    'getarnt als localhost' => 'http://localhost.example.com/callback',
    'fremder Host' => 'http://chatgpt.com/callback',
    'anderes Schema' => 'ftp://127.0.0.1/callback',
]);

it('führt einen nativen Client mit frei gewähltem Port bis zu einem Token', function (): void {
    Http::fake([CODEX_KENNUNG => Http::response(codexDokument())]);

    /*
     * Den Port kennt der Client erst beim Anmelden — er öffnet irgendeinen
     * freien. Im Dokument steht die Adresse ohne Port; League vergleicht bei
     * Loopback-Adressen ohne ihn, wie RFC 8252 es verlangt. Dieser Test hält
     * fest, dass beides zusammen bis zu einem Token trägt.
     */
    $rueckleitung = 'http://127.0.0.1:54321/callback/RfgAVj22ag3i';

    $benutzer = User::factory()->create();
    $pruefer = 'x'.str_repeat('b', 63);
    $frage = rtrim(strtr(base64_encode(hash('sha256', $pruefer, true)), '+/', '-_'), '=');

    $this->actingAs($benutzer)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => CODEX_KENNUNG,
            'redirect_uri' => $rueckleitung,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'zustand',
            'code_challenge' => $frage,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Codex');

    $weiterleitung = $this->actingAs($benutzer)
        ->post('/oauth/authorize', ['auth_token' => session('authToken'), 'state' => 'zustand'])
        ->assertRedirect()
        ->headers->get('Location');

    expect($weiterleitung)->toStartWith($rueckleitung);

    parse_str(parse_url($weiterleitung, PHP_URL_QUERY), $rueckgabe);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => CODEX_KENNUNG,
        'redirect_uri' => $rueckleitung,
        'code_verifier' => $pruefer,
        'code' => $rueckgabe['code'],
    ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
});

it('holt das Dokument so, dass die Adressbindung auch angewendet wird', function (): void {
    /*
     * Die Bindung an die geprüften Adressen (CURLOPT_RESOLVE) steht in den
     * cURL-Optionen. Der Stream-Handler von Guzzle kennt die nicht — mit
     * `stream => true` lief der Abruf über ihn, die Bindung war wirkungslos und
     * jeder Abruf brach mit einer Handler-Meldung ab. Kein Client konnte sich
     * verbinden.
     *
     * Gefangen hat das kein Test, weil `Http::fake()` den Handler ersetzt und
     * die Unverträglichkeit nie zum Tragen kam. Dieser Test sieht sich deshalb
     * die Optionen selbst an, die an Guzzle gehen.
     */
    $gesehen = null;

    Http::fake(function ($request, array $optionen) use (&$gesehen) {
        $gesehen = $optionen;

        return Http::response(dokument());
    });

    app(ResolveClientFromMetadataDocument::class)(KENNUNG);

    expect($gesehen)->not->toBeNull()
        ->and($gesehen['curl'] ?? [])->toHaveKey(CURLOPT_RESOLVE)
        // cURL-Optionen wirken nur ohne Stream-Handler.
        ->and($gesehen['stream'] ?? false)->toBeFalse();
});
