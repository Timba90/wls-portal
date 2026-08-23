<?php

use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Insights\GlobalSuchen;
use App\Mcp\Tools\Insights\HistorieLesen;
use App\Mcp\Tools\Insights\KennzahlenLesen;
use App\Mcp\Tools\Insights\NotizSpeichern;
use App\Mcp\Tools\Pricing\PreisaenderungAbbrechen;
use App\Mcp\Tools\Pricing\PreisaenderungPlanen;
use App\Mcp\Tools\Pricing\PreisDirektSetzen;
use App\Mcp\Tools\Pricing\PreisverlaufLesen;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Note;
use App\Models\PriceChange;
use App\Models\User;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
    $this->kunde = Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);
    $this->leistung = CustomerService::factory()->for($this->kunde)->create([
        'name' => 'Webhosting',
        'sales_price_cents' => 1990,
        'purchase_price_cents' => 450,
    ]);
});

it('plant eine Preisänderung für die Zukunft, ohne den Preis sofort zu ändern', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(PreisaenderungPlanen::class, [
            'leistung_id' => $this->leistung->id,
            'preisart' => 'sales',
            'neuer_preis_cents' => 2490,
            'wirksam_ab' => now()->addMonth()->toDateString(),
        ])
        ->assertOk();

    expect($this->leistung->refresh()->sales_price_cents)->toBe(1990)
        ->and(PriceChange::query()->where('customer_service_id', $this->leistung->id)->count())->toBe(1);
});

it('setzt eine für heute geplante Preisänderung sofort um', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(PreisaenderungPlanen::class, [
            'leistung_id' => $this->leistung->id,
            'preisart' => 'sales',
            'neuer_preis_cents' => 2490,
            'wirksam_ab' => now()->toDateString(),
        ])
        ->assertOk();

    expect($this->leistung->refresh()->sales_price_cents)->toBe(2490);
});

it('lehnt rückwirkende Preisänderungen ab', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(PreisaenderungPlanen::class, [
            'leistung_id' => $this->leistung->id,
            'preisart' => 'sales',
            'neuer_preis_cents' => 2490,
            'wirksam_ab' => now()->subDay()->toDateString(),
        ])
        ->assertHasErrors();

    expect($this->leistung->refresh()->sales_price_cents)->toBe(1990);
});

it('führt den Preisverlauf mit altem und neuem Preis', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(PreisaenderungPlanen::class, [
            'leistung_id' => $this->leistung->id,
            'preisart' => 'sales',
            'neuer_preis_cents' => 2490,
            'wirksam_ab' => now()->toDateString(),
        ])
        ->assertOk();

    PortalServer::actingAs($this->benutzer)
        ->tool(PreisverlaufLesen::class, ['leistung_id' => $this->leistung->id])
        ->assertOk()
        ->assertSee('1990')
        ->assertSee('2490');
});

it('bricht eine geplante Preisänderung ab', function (): void {
    $aenderung = PriceChange::factory()->for($this->leistung, 'customerService')->create([
        'effective_date' => now()->addMonth()->toDateString(),
        'applied_at' => null,
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(PreisaenderungAbbrechen::class, ['id' => $aenderung->id])
        ->assertOk();

    expect(PriceChange::query()->whereKey($aenderung->id)->exists())->toBeFalse();
});

it('lässt eine bereits wirksame Preisänderung bestehen', function (): void {
    $aenderung = PriceChange::factory()->for($this->leistung, 'customerService')->create([
        'effective_date' => now()->subMonth()->toDateString(),
        'applied_at' => now()->subMonth(),
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(PreisaenderungAbbrechen::class, ['id' => $aenderung->id])
        ->assertHasErrors();

    expect(PriceChange::query()->whereKey($aenderung->id)->exists())->toBeTrue();
});

it('überschreibt den Preis direkt, ohne Eintrag im Preisverlauf', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(PreisDirektSetzen::class, [
            'leistung_id' => $this->leistung->id,
            'preisart' => 'sales',
            'preis_cents' => 2490,
            'bestaetigung' => 'ohne-preisverlauf',
        ])
        ->assertOk();

    expect($this->leistung->refresh()->sales_price_cents)->toBe(2490)
        ->and(PriceChange::query()->where('customer_service_id', $this->leistung->id)->count())->toBe(0);
});

it('verlangt für das direkte Überschreiben die Bestätigung', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(PreisDirektSetzen::class, [
            'leistung_id' => $this->leistung->id,
            'preisart' => 'sales',
            'preis_cents' => 2490,
            'bestaetigung' => 'ja',
        ])
        ->assertHasErrors();

    expect($this->leistung->refresh()->sales_price_cents)->toBe(1990);
});

it('hält das direkte Überschreiben trotzdem in der Änderungshistorie fest', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(PreisDirektSetzen::class, [
            'leistung_id' => $this->leistung->id,
            'preisart' => 'sales',
            'preis_cents' => 2490,
            'bestaetigung' => 'ohne-preisverlauf',
        ])
        ->assertOk();

    PortalServer::actingAs($this->benutzer)
        ->tool(HistorieLesen::class, ['typ' => 'leistung', 'id' => $this->leistung->id])
        ->assertOk()
        ->assertSee('2490');
});

it('schützt archivierte Leistungen auch vor dem direkten Überschreiben', function (): void {
    $archiviert = CustomerService::factory()->for($this->kunde)->archived()->create([
        'sales_price_cents' => 1990,
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(PreisDirektSetzen::class, [
            'leistung_id' => $archiviert->id,
            'preisart' => 'sales',
            'preis_cents' => 999,
            'bestaetigung' => 'ohne-preisverlauf',
        ])
        ->assertHasErrors();

    expect($archiviert->refresh()->sales_price_cents)->toBe(1990);
});

it('liefert die Kennzahlen des Portals', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(KennzahlenLesen::class, [])
        ->assertOk()
        ->assertSee('umsatz')
        ->assertSee('marge');
});

it('findet über die globale Suche Kunden und Leistungen zugleich', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(GlobalSuchen::class, ['suchbegriff' => 'Nordlicht'])
        ->assertOk()
        ->assertSee('Nordlicht Medien');
});

it('legt eine Notiz an einem Kunden an', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(NotizSpeichern::class, [
            'typ' => 'kunde',
            'id' => $this->kunde->id,
            'kategorie' => 'billing',
            'text' => 'Rechnung geht an die Zentrale.',
        ])
        ->assertOk();

    expect(Note::query()->where('body', 'Rechnung geht an die Zentrale.')->exists())->toBeTrue();
});

it('überschreibt eine bestehende Notiz statt eine zweite anzulegen', function (): void {
    $notiz = $this->kunde->notes()->create(['category' => 'general', 'body' => 'Alt']);

    PortalServer::actingAs($this->benutzer)
        ->tool(NotizSpeichern::class, [
            'typ' => 'kunde',
            'id' => $this->kunde->id,
            'kategorie' => 'general',
            'text' => 'Neu',
            'notiz_id' => $notiz->id,
        ])
        ->assertOk();

    expect(Note::query()->count())->toBe(1)
        ->and($notiz->refresh()->body)->toBe('Neu');
});

it('verlangt für die Historie typ und id gemeinsam', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(HistorieLesen::class, ['typ' => 'kunde'])
        ->assertHasErrors();
});

it('liefert ohne Einschränkung den Gesamtstrom der Historie', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(HistorieLesen::class, [])
        ->assertOk()
        ->assertSee('created');
});
