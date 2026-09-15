<?php

use App\Http\Middleware\EnsureMcpScope;
use App\Mcp\Servers\PortalServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;

/*
|--------------------------------------------------------------------------
| MCP-Server
|--------------------------------------------------------------------------
|
| Der Portal-Server stellt Kunden, Ansprechpartner, Katalog, Leistungen,
| Preise und den Domainbestand als Werkzeuge bereit. Jeder Aufruf handelt im
| Namen eines Benutzers und erscheint unter dessen Namen in der
| Aenderungshistorie.
|
| Es gibt zwei Wege hinein:
|
| - Ein persoenliches Token aus `php artisan portal:mcp-token`. Einfach
|   einzurichten, traegt die vollen Rechte seines Benutzers.
| - OAuth. Der Client wird einmal mit `php artisan passport:client` angelegt,
|   verbindet sich dann selbst und bekommt vom Benutzer die Zustimmung —
|   widerrufbar, zeitlich begrenzt und auf `mcp:use` beschraenkt.
|
| Beide sind gleichzeitig zulaessig. Die Reihenfolge in `auth:sanctum,api` ist
| dabei keine Geschmacksfrage: andersherum beantwortet der OAuth-Guard den
| Versuch als erster, und ein persoenliches Token laeuft ins Leere.
|
*/

if (config('portal.mcp.enabled')) {
    Mcp::web(config('portal.mcp.path'), PortalServer::class)
        ->middleware([
            'auth:sanctum,api',
            EnsureMcpScope::class,
            'throttle:'.config('portal.mcp.rate_limit'),
        ])
        ->name('mcp.portal');

    /*
     * Die beiden Dokumente, mit denen ein Client den Server findet.
     *
     * Bewusst von Hand statt ueber `Mcp::oauthRoutes()`: das Paket haengt dort
     * `POST /oauth/register` an, die offene Selbstregistrierung nach
     * RFC 7591. Clients werden hier von Hand angelegt, und ein ohne Anmeldung
     * erreichbarer Endpunkt waere die einzige Ausnahme von der Regel, dass
     * ausser Anmeldung und Passwort-Ruecksetzung nichts offen ist.
     *
     * `registration_endpoint` fehlt deshalb im Dokument — das Feld ist in
     * RFC 8414 freigestellt, und ein Client, der es nicht findet, fragt nach
     * Zugangsdaten, statt sich selbst anzumelden.
     */
    Route::get('/.well-known/oauth-protected-resource/{pfad?}', function (?string $pfad = null) {
        return response()->json([
            'resource' => url('/'.($pfad ?? '')),
            'authorization_servers' => [url('/')],
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
        ]);
    })->where('pfad', '.*')->name('mcp.oauth.protected-resource');

    Route::get('/.well-known/oauth-authorization-server/{pfad?}', function (?string $pfad = null) {
        // Der Pfadteil steht in RFC 8414, aendert hier aber nichts: derselbe
        // Server, dieselben Endpunkte. Der Parameter wird nur angenommen,
        // damit die Absicht sichtbar ist.

        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
        ]);
    })->where('pfad', '.*')->name('mcp.oauth.authorization-server');
}
