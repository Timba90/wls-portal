<?php

use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Registrar\BestandSuchen;
use App\Mcp\Tools\Registrar\BestandZuordnen;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Models\User;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
    $this->kunde = Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);
});

it('findet Domains und Zertifikate zusammen', function (): void {
    Domain::factory()->create(['name' => 'nordlicht.de']);
    Certificate::factory()->create(['common_name' => 'www.nordlicht.de']);

    PortalServer::actingAs($this->benutzer)
        ->tool(BestandSuchen::class, ['suchbegriff' => 'nordlicht'])
        ->assertOk()
        ->assertSee('nordlicht.de')
        ->assertSee('www.nordlicht.de');
});

it('schraenkt auf einen Bestand ein', function (): void {
    Domain::factory()->create(['name' => 'nordlicht.de']);
    Certificate::factory()->create(['common_name' => 'www.nordlicht.de']);

    PortalServer::actingAs($this->benutzer)
        ->tool(BestandSuchen::class, ['typ' => 'domain'])
        ->assertOk()
        ->assertSee('nordlicht.de')
        ->assertDontSee('www.nordlicht.de');
});

it('nennt den offenen Rest nach einem Import', function (): void {
    Domain::factory()->create(['name' => 'offen.de']);
    Domain::factory()->create(['name' => 'zugeordnet.de', 'customer_id' => $this->kunde->id]);

    PortalServer::actingAs($this->benutzer)
        ->tool(BestandSuchen::class, ['zuordnung' => 'ohne_kunde'])
        ->assertOk()
        ->assertSee('offen.de')
        ->assertDontSee('zugeordnet.de');
});

it('nennt die Luecke zwischen Kunde und Abrechnung', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->create();

    Domain::factory()->create(['name' => 'ohne-leistung.de', 'customer_id' => $this->kunde->id]);
    Domain::factory()->create([
        'name' => 'abgerechnet.de',
        'customer_id' => $this->kunde->id,
        'customer_service_id' => $leistung->id,
    ]);
    Domain::factory()->create(['name' => 'ohne-kunde.de']);

    // Ohne Kunde ist etwas anderes als ohne Leistung: dort fehlt die Zuordnung
    // ganz, hier nur die Verbindung zur Abrechnung.
    PortalServer::actingAs($this->benutzer)
        ->tool(BestandSuchen::class, ['zuordnung' => 'ohne_leistung'])
        ->assertOk()
        ->assertSee('ohne-leistung.de')
        ->assertDontSee('abgerechnet.de')
        ->assertDontSee('ohne-kunde.de');
});

it('ordnet eine Domain ueber das Werkzeug zu', function (): void {
    $domain = Domain::factory()->create(['name' => 'nordlicht.de']);
    $leistung = CustomerService::factory()->for($this->kunde)->create(['name' => 'Domainpaket']);

    PortalServer::actingAs($this->benutzer)
        ->tool(BestandZuordnen::class, [
            'typ' => 'domain',
            'id' => $domain->id,
            'kunde_id' => $this->kunde->id,
            'leistung_id' => $leistung->id,
        ])
        ->assertOk()
        ->assertSee('Nordlicht Medien');

    expect($domain->refresh()->customer_id)->toBe($this->kunde->id)
        ->and($domain->customer_service_id)->toBe($leistung->id);
});

it('hebt die Zuordnung ohne Kunde wieder auf', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->create();
    $domain = Domain::factory()->create([
        'customer_id' => $this->kunde->id,
        'customer_service_id' => $leistung->id,
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(BestandZuordnen::class, ['typ' => 'domain', 'id' => $domain->id])
        ->assertOk();

    expect($domain->refresh()->customer_id)->toBeNull()
        ->and($domain->customer_service_id)->toBeNull();
});

it('weist eine Leistung eines anderen Kunden ab', function (): void {
    $domain = Domain::factory()->create();
    $fremdeLeistung = CustomerService::factory()->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(BestandZuordnen::class, [
            'typ' => 'domain',
            'id' => $domain->id,
            'kunde_id' => $this->kunde->id,
            'leistung_id' => $fremdeLeistung->id,
        ])
        ->assertHasErrors();

    expect($domain->refresh()->customer_id)->toBeNull();
});

it('ordnet auch ein Zertifikat zu', function (): void {
    $zertifikat = Certificate::factory()->create(['common_name' => 'www.nordlicht.de']);

    PortalServer::actingAs($this->benutzer)
        ->tool(BestandZuordnen::class, [
            'typ' => 'zertifikat',
            'id' => $zertifikat->id,
            'kunde_id' => $this->kunde->id,
        ])
        ->assertOk()
        ->assertSee('www.nordlicht.de');

    expect($zertifikat->refresh()->customer_id)->toBe($this->kunde->id);
});

it('meldet einen unbekannten Eintrag, statt still nichts zu tun', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(BestandZuordnen::class, ['typ' => 'domain', 'id' => 999999, 'kunde_id' => $this->kunde->id])
        ->assertHasErrors();
});
