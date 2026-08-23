<?php

use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Customers\KundeArchivieren;
use App\Mcp\Tools\Customers\KundeLesen;
use App\Mcp\Tools\Customers\KundeLoeschen;
use App\Mcp\Tools\Customers\KundenSuchen;
use App\Mcp\Tools\Customers\KundeSpeichern;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Note;
use App\Models\User;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('filtert die Kundenliste nach Typ und Status', function (): void {
    Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);
    Customer::factory()->privatePerson()->create(['last_name' => 'Sonderweg']);
    Customer::factory()->company()->archived()->create(['company_name' => 'Altbestand']);

    PortalServer::actingAs($this->benutzer)
        ->tool(KundenSuchen::class, ['typ' => 'company', 'status' => 'active'])
        ->assertOk()
        ->assertSee('Nordlicht Medien')
        ->assertDontSee('Sonderweg')
        ->assertDontSee('Altbestand');
});

it('liefert ohne Statusfilter auch archivierte Kunden', function (): void {
    Customer::factory()->company()->archived()->create(['company_name' => 'Altbestand']);

    PortalServer::actingAs($this->benutzer)
        ->tool(KundenSuchen::class, [])
        ->assertOk()
        ->assertSee('Altbestand');
});

it('liest einen Kunden über die Kundennummer statt über die ID', function (): void {
    $kunde = Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);

    PortalServer::actingAs($this->benutzer)
        ->tool(KundeLesen::class, ['kundennummer' => $kunde->customer_number])
        ->assertOk()
        ->assertSee('Nordlicht Medien');
});

it('meldet einen unbekannten Kunden als Fehler statt leer zu antworten', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(KundeLesen::class, ['id' => 999999])
        ->assertHasErrors();
});

it('legt einen Firmenkunden mit Kundennummer an', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(KundeSpeichern::class, [
            'typ' => 'company',
            'firmenname' => 'Werft & Partner',
            'kurzbezeichnung' => 'Werft',
            'internes_kuerzel' => 'WRF',
        ])
        ->assertOk()
        ->assertSee('angelegt');

    $kunde = Customer::query()->where('company_name', 'Werft & Partner')->firstOrFail();

    expect($kunde->customer_number)->toStartWith('KD-')
        ->and($kunde->short_label)->toBe('Werft');
});

it('verlangt beim Anlegen eines Firmenkunden einen Firmennamen', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(KundeSpeichern::class, [
            'typ' => 'company',
            'kurzbezeichnung' => 'Ohne',
            'internes_kuerzel' => 'OHN',
        ])
        ->assertHasErrors();
});

it('behält beim Ändern die nicht angegebenen Felder', function (): void {
    $kunde = Customer::factory()->company()->create([
        'company_name' => 'Nordlicht Medien',
        'short_label' => 'Nordlicht',
        'internal_code' => 'NLM',
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(KundeSpeichern::class, ['id' => $kunde->id, 'firmenname' => 'Nordlicht Medien GmbH'])
        ->assertOk();

    $kunde->refresh();

    expect($kunde->company_name)->toBe('Nordlicht Medien GmbH')
        ->and($kunde->short_label)->toBe('Nordlicht')
        ->and($kunde->internal_code)->toBe('NLM');
});

it('archiviert einen Kunden und hebt die Archivierung wieder auf', function (): void {
    $kunde = Customer::factory()->company()->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(KundeArchivieren::class, ['id' => $kunde->id])
        ->assertOk();

    expect($kunde->refresh()->isArchived())->toBeTrue();

    PortalServer::actingAs($this->benutzer)
        ->tool(KundeArchivieren::class, ['id' => $kunde->id, 'archivieren' => false])
        ->assertOk();

    expect($kunde->refresh()->isArchived())->toBeFalse();
});

it('löscht einen Kunden nur mit passender Bestätigung', function (): void {
    $kunde = Customer::factory()->company()->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(KundeLoeschen::class, ['id' => $kunde->id, 'bestaetigung' => 'KD-99999'])
        ->assertHasErrors();

    expect(Customer::query()->whereKey($kunde->id)->exists())->toBeTrue();
});

it('entfernt beim Löschen auch Leistungen und Notizen', function (): void {
    $kunde = Customer::factory()->company()->create();
    $leistung = CustomerService::factory()->for($kunde)->create();
    $kunde->notes()->create(['category' => 'general', 'body' => 'Zu entfernen']);

    PortalServer::actingAs($this->benutzer)
        ->tool(KundeLoeschen::class, [
            'id' => $kunde->id,
            'bestaetigung' => $kunde->customer_number,
        ])
        ->assertOk();

    expect(Customer::query()->whereKey($kunde->id)->exists())->toBeFalse()
        ->and(CustomerService::query()->whereKey($leistung->id)->exists())->toBeFalse()
        ->and(Note::query()->count())->toBe(0);
});

it('bewahrt die Änderungshistorie eines gelöschten Kunden', function (): void {
    $kunde = Customer::factory()->company()->create();
    $kundennummer = $kunde->customer_number;

    PortalServer::actingAs($this->benutzer)
        ->tool(KundeLoeschen::class, ['id' => $kunde->id, 'bestaetigung' => $kundennummer])
        ->assertOk();

    expect(AuditLog::query()
        ->where('auditable_type', (new Customer)->getMorphClass())
        ->where('auditable_id', $kunde->id)
        ->where('event', 'deleted')
        ->exists())->toBeTrue();
});
