<?php

use Illuminate\Support\Facades\File;

/**
 * Wächter über die JavaScript-Schnittstelle von TallStackUI.
 *
 * Diese Aufrufe stehen in Blade-Attributen und laufen erst im Browser. Ein
 * falscher Name fällt weder beim Kompilieren noch in einem Feature-Test auf:
 * die Seite lädt, der Knopf ist da, und beim Klick passiert nichts. Genau so
 * waren sämtliche Bestätigungsdialoge und Hinweise der Anwendung monatelang
 * wirkungslos.
 *
 * @return array<int, string>
 */
function bladeAnsichten(): array
{
    return collect(File::allFiles(resource_path('views')))
        ->filter(fn ($datei): bool => str_ends_with($datei->getFilename(), '.blade.php'))
        ->map(fn ($datei): string => $datei->getPathname())
        ->values()
        ->all();
}

it('ruft keine Schnittstelle auf, die es in TallStackUI 3 nicht gibt', function (): void {
    // `$dialog` und `$tallstackui` waren die Namen der Vorgängerversion.
    $veraltet = ['$dialog', '$tallstackui', '$toast'];

    $fundstellen = [];

    foreach (bladeAnsichten() as $pfad) {
        $inhalt = File::get($pfad);

        foreach ($veraltet as $name) {
            if (str_contains($inhalt, $name)) {
                $fundstellen[] = str_replace(resource_path('views').'/', '', $pfad).': '.$name;
            }
        }
    }

    expect($fundstellen)->toBe([]);
});

it('nennt bei jedem Dialog mit Livewire-Methode auch die Komponente', function (): void {
    // Ohne `wireable()` weist TallStackUI den Dialog still ab: der Knopf
    // reagiert, und dann geschieht nichts.
    $ohneBezug = [];

    foreach (bladeAnsichten() as $pfad) {
        $inhalt = File::get($pfad);

        preg_match_all(
            "/\\\$tsui\.interaction\('dialog'\)(.*?)\.send\(\)/s",
            $inhalt,
            $treffer,
        );

        foreach ($treffer[1] as $block) {
            $mitMethode = preg_match("/\.(confirm|cancel)\([^)]*,\s*'/", $block) === 1;

            if ($mitMethode && ! str_contains($block, '.wireable(')) {
                $ohneBezug[] = str_replace(resource_path('views').'/', '', $pfad);
            }
        }
    }

    expect($ohneBezug)->toBe([]);
});

it('findet die Bestätigungsdialoge, die es prüfen soll', function (): void {
    // Ohne diese Zusicherung wären die beiden Tests oben auch dann grün, wenn
    // gar kein Dialog mehr in der Anwendung stünde.
    $dialoge = collect(bladeAnsichten())
        ->sum(fn (string $pfad): int => substr_count(File::get($pfad), "\$tsui.interaction('dialog')"));

    expect($dialoge)->toBeGreaterThanOrEqual(10);
});
