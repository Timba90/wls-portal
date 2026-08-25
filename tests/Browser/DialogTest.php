<?php

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Note;
use App\Models\User;

/**
 * Bestätigungsdialoge im echten Browser.
 *
 * Diese Aufrufe stehen in Blade-Attributen und laufen erst im Browser. Ein
 * falscher Name fällt weder beim Kompilieren noch in einem Feature-Test auf:
 * die Seite lädt, der Knopf ist da, und beim Klick geschieht nichts. Genau so
 * war eine Zeit lang jeder Dialog der Anwendung wirkungslos.
 *
 * Der bestätigende Knopf wird über den Dialograhmen angesteuert, nicht über
 * seinen Text allein — „Archivieren" steht auch auf dem auslösenden Knopf
 * dahinter.
 */
function dialogKnopf(string $beschriftung): string
{
    return '[x-data^="tallstackui_dialog"] button:has-text("'.$beschriftung.'")';
}

it('archiviert eine Leistung über den Bestätigungsdialog', function (): void {
    $this->actingAs(User::factory()->create());

    $leistung = CustomerService::factory()->create(['name' => 'Webhosting Probelauf']);

    visit("/kunden/{$leistung->customer_id}/leistungen/{$leistung->id}")
        ->click('Leistung archivieren')
        ->waitForText('Archivierte Leistungen sind vollständig schreibgeschützt')
        ->click(dialogKnopf('Archivieren'))
        ->waitForText('Archivierung aufheben')
        ->assertNoJavaScriptErrors();

    expect($leistung->fresh()->isArchived())->toBeTrue();
});

it('archiviert einen Kunden über den Bestätigungsdialog', function (): void {
    $this->actingAs(User::factory()->create());

    $kunde = Customer::factory()->create(['company_name' => 'Dialogprobe GmbH']);

    visit("/kunden/{$kunde->id}")
        ->click('Archivieren')
        ->waitForText('Kunde archivieren?')
        ->click(dialogKnopf('Archivieren'))
        ->waitForText('Archivierung aufheben')
        ->assertNoJavaScriptErrors();

    expect($kunde->fresh()->isArchived())->toBeTrue();
});

it('gibt einen Abbruch im Dialog auch als Abbruch weiter', function (): void {
    $this->actingAs(User::factory()->create());

    $kunde = Customer::factory()->create(['company_name' => 'Abbruchprobe GmbH']);

    visit("/kunden/{$kunde->id}")
        ->click('Archivieren')
        ->waitForText('Kunde archivieren?')
        ->click(dialogKnopf('Abbrechen'))
        ->assertNoJavaScriptErrors();

    expect($kunde->fresh()->isArchived())->toBeFalse();
});

it('löscht eine Notiz über den Dialog samt Parameter', function (): void {
    $benutzer = User::factory()->create();
    $this->actingAs($benutzer);

    $kunde = Customer::factory()->create();
    $notiz = Note::factory()->create([
        'notable_type' => Customer::class,
        'notable_id' => $kunde->id,
        'body' => 'Diese Notiz wird im Test gelöscht.',
        'user_id' => $benutzer->id,
    ]);

    visit("/kunden/{$kunde->id}")
        ->click('Notizen')
        ->waitForText('Diese Notiz wird im Test gelöscht.')
        ->click('[x-on\\:click*="Notiz löschen"]')
        ->waitForText('Die Notiz wird endgültig entfernt')
        ->click(dialogKnopf('Löschen'))
        // Auf die Antwort warten, sonst prüft der Test schneller, als
        // gelöscht wird.
        ->waitForText('Noch keine Notizen erfasst')
        ->assertNoJavaScriptErrors();

    expect(Note::find($notiz->id))->toBeNull();
});
