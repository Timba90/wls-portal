<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/**
 * Anwendungsseiten, die absichtlich ohne Anmeldung erreichbar sind.
 *
 * Alles andere muss den Gast zur Anmeldung schicken. Wer hier etwas einträgt,
 * trifft eine bewusste Entscheidung — genau dafür gibt es die Liste.
 */
const OEFFENTLICHE_SEITEN = [
    // Weiterleitung auf das Dashboard; die Anmeldung greift dahinter.
    '/',

    // Anmeldung und Passwort-Zurücksetzung (Fortify).
    'login',
    'forgot-password',
    'reset-password/{token}',
    'two-factor-challenge',

    // Der MCP-Zugang authentifiziert über ein Token, nicht über die Sitzung.
    'mcp/portal',
];

/**
 * Pfadanfänge, die nicht zur Anwendung gehören: Auslieferung von Skripten und
 * Stilen, Infrastruktur des Frameworks, Entwicklungswerkzeuge.
 */
const FREMDE_PFADE = [
    'livewire-',
    'livewire/',
    'tallstackui/',
    'sanctum/',
    'storage/',
    'up',
    '_boost/',
    '_debugbar',
    'horizon',
];

/**
 * Die Routen, die eine Anmeldung verlangen müssen.
 *
 * @return array<int, RouteDefinition>
 */
function geschuetzteRouten(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => in_array('GET', $route->methods(), true))
        ->reject(fn (RouteDefinition $route): bool => in_array($route->uri(), OEFFENTLICHE_SEITEN, true))
        ->reject(function (RouteDefinition $route): bool {
            foreach (FREMDE_PFADE as $pfad) {
                if (str_starts_with($route->uri(), $pfad)) {
                    return true;
                }
            }

            return false;
        })
        ->values()
        ->all();
}

it('schickt Gäste auf jeder Seite zur Anmeldung', function (): void {
    $ungeschuetzt = [];

    foreach (geschuetzteRouten() as $route) {
        // Platzhalter füllen: die Anmeldeprüfung läuft vor der Modellbindung,
        // ein Gast darf also nie bis zu einem 404 durchkommen.
        $pfad = preg_replace('/\{[^}]+\}/', '1', $route->uri());

        $antwort = $this->get('/'.ltrim((string) $pfad, '/'));

        if (! $antwort->isRedirect(route('login'))) {
            $ungeschuetzt[] = $route->uri().' → '.$antwort->getStatusCode();
        }
    }

    expect($ungeschuetzt)->toBe([]);
});

it('prüft dabei alle Seiten der Anwendung', function (): void {
    $geprueft = collect(geschuetzteRouten())->map(fn (RouteDefinition $route): string => $route->uri());

    // Untergrenze statt fester Zahl: der Test soll bei einer neuen Seite nicht
    // umfallen, aber merken, wenn das Filtern einmal zu viel wegwirft.
    expect($geprueft->count())->toBeGreaterThanOrEqual(25)
        ->and($geprueft)->toContain('dashboard', 'kunden', 'projekte', 'leistungen', 'benutzer')
        ->and($geprueft)->toContain('dokumente/{document}/versionen/{version}/download');
});
