<?php

use App\Models\User;

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
