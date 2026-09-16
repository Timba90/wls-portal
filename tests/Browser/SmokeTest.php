<?php

use App\Enums\RegistrarProvider;
use App\Models\Domain;
use App\Models\RegistrarSync;
use App\Models\User;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\ClientRepository;

/**
 * Rundgang durch die Anwendung im echten Browser.
 *
 * Die Feature-Tests bauen Livewire-Komponenten serverseitig auf und sehen
 * deshalb nicht, ob im Browser etwas bricht: ein Alpine-Fehler, ein fehlendes
 * Asset, ein Skript, das über einer Nullreferenz stolpert. Genau das fängt
 * dieser Rundgang ab — er ersetzt die Handprüfung, die ich sonst jedes Mal
 * per Playwright gefahren habe.
 */
it('lädt jede Seite ohne Fehler in der Konsole', function (): void {
    $this->actingAs(User::factory()->create());

    $seiten = visit([
        '/dashboard',
        '/kunden',
        '/kunden/neu',
        '/projekte',
        '/projekte/neu',
        '/projekte/typen',
        '/leistungen',
        '/ansprechpartner',
        '/ansprechpartner/neu',
        '/ansprechpartner/rollen',
        '/artikel',
        '/artikel/neu',
        '/artikel/kategorien',
        '/domains',
        '/zertifikate',
        '/archiv',
        '/benutzer',
        '/felder',
        '/profil',
        '/profil/sicherheit',
    ]);

    $seiten->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

it('zeigt die Anmeldeseite ohne Fehler', function (): void {
    visit('/login')
        ->assertSee('weblab studio')
        ->assertNoJavaScriptErrors();
});

it('zeigt die Zustimmungsseite von OAuth ohne Fehler', function (): void {
    $benutzer = User::factory()->create();

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Claude',
        ['https://client.test/rueckruf'],
        confidential: false,
    );

    $this->actingAs($benutzer);

    // Die Seite steht im Layout der Anmeldung und wird sonst von keinem
    // Rundgang berührt.
    visit('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/rueckruf',
        'response_type' => 'code',
        'scope' => Registrar::OAUTH_SCOPE,
        'state' => 'zustand',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]))
        ->assertSee('Zugriff erlauben?')
        ->assertSee('Claude')
        ->assertSee('Erlauben')
        ->assertNoJavaScriptErrors();
});

it('zeigt einen fehlgeschlagenen Abgleich unter Schnittstellen', function (): void {
    RegistrarSync::query()->create([
        'provider' => RegistrarProvider::AutoDns->value,
        'trigger' => 'scheduled',
        'started_at' => now()->subHours(6),
        'finished_at' => now()->subHours(6),
        'error' => 'autoDNS meldet zu domain/_search: Authorization failed (EF01001)',
    ]);

    $this->actingAs(User::factory()->create());

    // Der Kasten ist rot eingefärbt und steht sonst in keinem Rundgang.
    visit('/schnittstellen')
        ->assertSee('Letzter Abgleich fehlgeschlagen')
        ->assertSee('Authorization failed')
        ->assertNoJavaScriptErrors();
});

it('wechselt auf der Domainseite zwischen den Bereichen', function (): void {
    $this->actingAs(User::factory()->create());

    $domain = Domain::factory()->create(['name' => 'rundgang.de']);

    visit("/domains/{$domain->id}")
        ->assertSee('rundgang.de')
        ->assertSee('Technischer Stand')
        ->click('Dokumente')
        ->waitForText('Noch keine Dokumente')
        ->click('Verlauf')
        ->waitForText('Änderungen an dieser Domain')
        ->assertNoJavaScriptErrors();
});
