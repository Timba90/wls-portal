<?php

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;

/**
 * Formulare in Modals, im echten Browser geöffnet.
 *
 * Ein Modal, das sich nicht öffnet, sieht auf einem serverseitig gerenderten
 * Test genauso aus wie eines, das sich öffnet: das Markup steht in beiden
 * Fällen in der Seite. Erst der Browser zeigt den Unterschied.
 *
 * Geprüft wird jeweils auf Text aus dem Inhalt, nicht auf den Rahmen — der
 * Rahmen ist auch im geschlossenen Zustand vorhanden und null Pixel hoch.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('öffnet die Formulare im Leistungsdetail', function (): void {
    $leistung = CustomerService::factory()->create();
    $adresse = "/kunden/{$leistung->customer_id}/leistungen/{$leistung->id}";

    // Über den Aufruf statt über die Beschriftung: „Nicht abrechnen" steht
    // an mehreren Stellen der Seite.
    visit($adresse)
        ->click('[wire\\:click="$set(\'showDoNotBillForm\', true)"]')
        ->waitForText('Die Kennzeichnung gilt, bis sie manuell entfernt wird')
        ->assertNoJavaScriptErrors();

    visit($adresse)
        ->click('Preis anpassen')
        ->waitForText('Preisänderung')
        ->assertNoJavaScriptErrors();
});

it('öffnet Meilenstein- und Positionsformular im Projektdetail', function (): void {
    $projekt = Project::factory()->create();

    visit("/projekte/{$projekt->id}")
        ->click('[wire\\:click="openMilestoneForm"]')
        ->waitForText('Meilenstein anlegen')
        ->assertNoJavaScriptErrors();

    // Der Knopf liegt hinter dem Reiter „Positionen".
    visit("/projekte/{$projekt->id}")
        ->click('[wire\\:click="$set(\'tab\', \'positionen\')"]')
        ->waitForText('Positionen aus Katalog')
        ->click('[wire\\:click="openPositionForm"]')
        ->waitForText('Position anlegen')
        ->assertNoJavaScriptErrors();
});

it('öffnet das Variantenformular im Artikeldetail', function (): void {
    $artikel = Product::factory()->create();

    visit("/artikel/{$artikel->id}")
        ->click('Variante anlegen')
        ->waitForText('Leer lassen, um den Artikelwert zu übernehmen')
        ->assertNoJavaScriptErrors();
});

it('öffnet das Notizformular', function (): void {
    $kunde = Customer::factory()->create();

    visit("/kunden/{$kunde->id}")
        ->click('Notizen')
        ->click('[wire\\:click="create"]')
        ->waitForText('Notiz anlegen')
        ->assertNoJavaScriptErrors();
});

it('öffnet die Formulare der Katalog- und Systemlisten', function (string $pfad, string $inhalt): void {
    visit($pfad)
        ->click('[wire\\:click="create"]')
        ->waitForText($inhalt)
        ->assertNoJavaScriptErrors();
})->with([
    'Kategorien' => ['/artikel/kategorien', 'Kategorie anlegen'],
    'Tags' => ['/artikel/tags', 'Tag anlegen'],
    'Projekttypen' => ['/projekte/typen', 'Projekttyp anlegen'],
    'Benutzer' => ['/benutzer', 'Benutzer anlegen'],
]);
