<?php

use App\Actions\Contacts\CreateContact;
use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Contacts\AnsprechpartnerArchivieren;
use App\Mcp\Tools\Contacts\AnsprechpartnerLesen;
use App\Mcp\Tools\Contacts\AnsprechpartnerLoeschen;
use App\Mcp\Tools\Contacts\AnsprechpartnerSpeichern;
use App\Mcp\Tools\Contacts\AnsprechpartnerSuchen;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\Customer;
use App\Models\User;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
    $this->kunde = Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);
});

/**
 * Legt einen Ansprechpartner samt Zuordnung an — Zuordnungen entstehen im
 * Projekt ausschliesslich ueber die Action, nicht ueber eine Factory.
 *
 * @param  array<int, array<string, mixed>>  $emails
 */
function ansprechpartnerAnlegen(Customer $kunde, string $nachname, array $emails = []): Contact
{
    return app(CreateContact::class)(
        attributes: ['first_name' => 'Merle', 'last_name' => $nachname],
        assignments: [['customer_id' => $kunde->id]],
        emails: $emails,
    );
}

it('schränkt die Suche auf einen Kunden ein', function (): void {
    ansprechpartnerAnlegen($this->kunde, 'Ahrens');
    ansprechpartnerAnlegen(Customer::factory()->company()->create(), 'Bergmann');

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerSuchen::class, ['kunde_id' => $this->kunde->id])
        ->assertOk()
        ->assertSee('Ahrens')
        ->assertDontSee('Bergmann');
});

it('umgeht mit dem Kundenfilter nicht den Suchbegriff', function (): void {
    ansprechpartnerAnlegen($this->kunde, 'Ahrens');
    ansprechpartnerAnlegen(Customer::factory()->company()->create(), 'Ahrens');

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerSuchen::class, [
            'suchbegriff' => 'Ahrens',
            'kunde_id' => $this->kunde->id,
        ])
        ->assertOk()
        ->assertSee('"anzahl":1');
});

it('findet einen Ansprechpartner über die E-Mail-Adresse', function (): void {
    ansprechpartnerAnlegen($this->kunde, 'Ahrens', [
        ['email' => 'merle@nordlicht.test', 'type' => 'business', 'is_primary' => true],
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerSuchen::class, ['suchbegriff' => 'nordlicht.test'])
        ->assertOk()
        ->assertSee('Ahrens');
});

it('legt einen Ansprechpartner mit Kundenzuordnung an', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerSpeichern::class, [
            'vorname' => 'Merle',
            'nachname' => 'Ahrens',
            'zuordnungen' => [['kunde_id' => $this->kunde->id, 'hauptansprechpartner' => true]],
            'email_adressen' => [['email' => 'ahrens@example.test', 'art' => 'business', 'primaer' => true]],
        ])
        ->assertOk()
        ->assertSee('angelegt');

    $kontakt = Contact::query()->where('last_name', 'Ahrens')->firstOrFail();

    expect($kontakt->assignments)->toHaveCount(1)
        ->and($kontakt->primaryEmailAddress()?->email)->toBe('ahrens@example.test');
});

it('verlangt beim Anlegen mindestens eine Kundenzuordnung', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerSpeichern::class, ['vorname' => 'Merle', 'nachname' => 'Ahrens'])
        ->assertHasErrors();
});

it('behält beim Ändern die bestehenden Zuordnungen und Kanäle', function (): void {
    $kontakt = ansprechpartnerAnlegen($this->kunde, 'Ahrens', [
        ['email' => 'ahrens@example.test', 'type' => 'business', 'is_primary' => true],
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerSpeichern::class, ['id' => $kontakt->id, 'vorname' => 'Merle-Sophie'])
        ->assertOk();

    $kontakt->refresh()->load(['assignments', 'emailAddresses']);

    expect($kontakt->first_name)->toBe('Merle-Sophie')
        ->and($kontakt->assignments)->toHaveCount(1)
        ->and($kontakt->emailAddresses)->toHaveCount(1);
});

it('liest einen Ansprechpartner mit seinen Zuordnungen', function (): void {
    $kontakt = ansprechpartnerAnlegen($this->kunde, 'Ahrens');

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerLesen::class, ['id' => $kontakt->id])
        ->assertOk()
        ->assertSee('Ahrens')
        ->assertSee('Nordlicht Medien');
});

it('archiviert einen Ansprechpartner und behält die Zuordnungen', function (): void {
    $kontakt = ansprechpartnerAnlegen($this->kunde, 'Ahrens');

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerArchivieren::class, ['id' => $kontakt->id])
        ->assertOk();

    expect($kontakt->refresh()->isArchived())->toBeTrue()
        ->and($kontakt->assignments()->count())->toBe(1);
});

it('entfernt einen Ansprechpartner endgültig samt Zuordnungen', function (): void {
    $kontakt = ansprechpartnerAnlegen($this->kunde, 'Ahrens');

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerLoeschen::class, ['id' => $kontakt->id, 'bestaetigung' => 'Ahrens'])
        ->assertOk();

    expect(Contact::query()->whereKey($kontakt->id)->exists())->toBeFalse()
        ->and(ContactAssignment::query()->where('contact_id', $kontakt->id)->exists())->toBeFalse();
});

it('verweigert das Löschen bei falscher Bestätigung', function (): void {
    $kontakt = ansprechpartnerAnlegen($this->kunde, 'Ahrens');

    PortalServer::actingAs($this->benutzer)
        ->tool(AnsprechpartnerLoeschen::class, ['id' => $kontakt->id, 'bestaetigung' => 'Falsch'])
        ->assertHasErrors();

    expect(Contact::query()->whereKey($kontakt->id)->exists())->toBeTrue();
});
