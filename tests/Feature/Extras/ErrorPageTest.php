<?php

use App\Models\User;

it('zeigt eine eigene Seite statt der Laravel-Standardseite', function (string $datei, string $code, string $ueberschrift): void {
    $html = view('errors.'.$datei)->render();

    expect($html)->toContain($code)
        ->and($html)->toContain($ueberschrift)
        // Das dunkle Schema der Anwendung, nicht Laravels helle Standardseite.
        ->and($html)->toContain('class="dark h-full"');
})->with([
    'nicht gefunden' => ['404', '404', 'Diese Seite gibt es nicht'],
    'nicht erlaubt' => ['403', '403', 'Zugriff nicht möglich'],
    'Sitzung abgelaufen' => ['419', '419', 'Sitzung abgelaufen'],
    'Serverfehler' => ['500', '500', 'Da ist etwas schiefgelaufen'],
    'Wartung' => ['503', '503', 'Kurz nicht erreichbar'],
]);

it('führt bei abgelaufener Sitzung zur Anmeldung', function (): void {
    // Die häufigste Fehlerseite im Alltag: 30 Minuten ohne Aktivität, dann
    // ein Formular aus einem alten Tab. Der Weg zurück muss die Anmeldung sein.
    $html = view('errors.419')->render();

    expect($html)->toContain(route('login'))
        ->and($html)->toContain('Erneut anmelden');
});

it('schickt Gäste zur Anmeldung und Angemeldete zur Übersicht', function (): void {
    expect(view('errors.404')->render())->toContain(route('login'));

    $this->actingAs(User::factory()->create());

    expect(view('errors.404')->render())->toContain(route('dashboard'))
        ->and(view('errors.404')->render())->toContain('Zur Übersicht');
});

it('liefert die eigene Seite auch über eine echte Anfrage aus', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/kunden/999999')
        ->assertNotFound()
        ->assertSee('Diese Seite gibt es nicht');
});
