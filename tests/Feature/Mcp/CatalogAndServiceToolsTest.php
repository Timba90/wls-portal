<?php

use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Catalog\KatalogSuchen;
use App\Mcp\Tools\Catalog\ProduktLesen;
use App\Mcp\Tools\Catalog\ProduktLoeschen;
use App\Mcp\Tools\Catalog\ProduktSpeichern;
use App\Mcp\Tools\Services\LeistungenSuchen;
use App\Mcp\Tools\Services\LeistungLesen;
use App\Mcp\Tools\Services\LeistungLoeschen;
use App\Mcp\Tools\Services\LeistungSpeichern;
use App\Mcp\Tools\Services\LeistungStatusSetzen;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
    $this->kunde = Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);
});

it('durchsucht den Katalog und blendet archivierte Artikel auf Wunsch aus', function (): void {
    Product::factory()->create(['name' => 'Webhosting Basis']);
    Product::factory()->archived()->create(['name' => 'Altvertrag Hosting']);

    PortalServer::actingAs($this->benutzer)
        ->tool(KatalogSuchen::class, ['status' => 'active'])
        ->assertOk()
        ->assertSee('Webhosting Basis')
        ->assertDontSee('Altvertrag Hosting');
});

it('legt einen Katalogartikel mit Preisen in Cent an', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(ProduktSpeichern::class, [
            'name' => 'Webhosting Basis',
            'interner_name' => 'hosting-basis',
            'einkaufspreis_cents' => 450,
            'verkaufspreis_cents' => 1990,
            'abrechnungsintervall_einheit' => 'month',
        ])
        ->assertOk();

    $produkt = Product::query()->where('internal_name', 'hosting-basis')->firstOrFail();

    expect($produkt->default_purchase_price_cents)->toBe(450)
        ->and($produkt->default_sales_price_cents)->toBe(1990)
        ->and($produkt->defaultMargin()->cents)->toBe(1540);
});

it('rechnet auch bei krummen Centbeträgen exakt', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(ProduktSpeichern::class, [
            'name' => 'Sonderposten',
            'interner_name' => 'sonderposten',
            'verkaufspreis_cents' => 123457,
            'abrechnungsintervall_einheit' => 'year',
        ])
        ->assertOk();

    expect(Product::query()->where('internal_name', 'sonderposten')->value('default_sales_price_cents'))
        ->toBe(123457);
});

it('behält beim Ändern eines Artikels die nicht angegebenen Werte', function (): void {
    $produkt = Product::factory()->create([
        'name' => 'Webhosting Basis',
        'internal_name' => 'hosting-basis',
        'default_sales_price_cents' => 1990,
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProduktSpeichern::class, ['id' => $produkt->id, 'name' => 'Webhosting Standard'])
        ->assertOk();

    $produkt->refresh();

    expect($produkt->name)->toBe('Webhosting Standard')
        ->and($produkt->internal_name)->toBe('hosting-basis')
        ->and($produkt->default_sales_price_cents)->toBe(1990);
});

it('liest einen Artikel mit Varianten', function (): void {
    $produkt = Product::factory()->create(['name' => 'Webhosting Basis']);
    ProductVariant::factory()->for($produkt)->create(['name' => 'Doppelter Speicher']);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProduktLesen::class, ['id' => $produkt->id])
        ->assertOk()
        ->assertSee('Doppelter Speicher');
});

it('entkoppelt beim Löschen eines Artikels die Kundenleistungen, statt sie zu entfernen', function (): void {
    $produkt = Product::factory()->create(['internal_name' => 'hosting-basis']);
    $leistung = CustomerService::factory()->for($this->kunde)->for($produkt)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProduktLoeschen::class, ['id' => $produkt->id, 'bestaetigung' => 'hosting-basis'])
        ->assertOk();

    $leistung->refresh();

    expect(Product::query()->whereKey($produkt->id)->exists())->toBeFalse()
        ->and($leistung->exists)->toBeTrue()
        ->and($leistung->product_id)->toBeNull();
});

it('legt eine Kundenleistung an und berechnet die Marge', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungSpeichern::class, [
            'kunde_id' => $this->kunde->id,
            'name' => 'Webhosting',
            'einkaufspreis_cents' => 450,
            'verkaufspreis_cents' => 1990,
            'abrechnungsintervall_einheit' => 'month',
        ])
        ->assertOk();

    $leistung = CustomerService::query()->where('name', 'Webhosting')->firstOrFail();

    expect($leistung->margin()->cents)->toBe(1540)
        ->and($leistung->monthlyRevenue()->cents)->toBe(1990);
});

it('rechnet den Jahresbeitrag korrekt auf den Monat um', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungSpeichern::class, [
            'kunde_id' => $this->kunde->id,
            'name' => 'Domain',
            'verkaufspreis_cents' => 1200,
            'abrechnungsintervall_einheit' => 'year',
        ])
        ->assertOk();

    expect(CustomerService::query()->where('name', 'Domain')->firstOrFail()->monthlyRevenue()->cents)
        ->toBe(100);
});

it('weist Änderungen an archivierten Leistungen ab', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->archived()->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungSpeichern::class, ['id' => $leistung->id, 'name' => 'Neuer Name'])
        ->assertHasErrors();
});

it('reaktiviert eine archivierte Leistung über den Status', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->archived()->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungStatusSetzen::class, ['id' => $leistung->id, 'status' => 'active'])
        ->assertOk();

    expect($leistung->refresh()->isArchived())->toBeFalse();
});

it('verlangt für „bewusst nicht abrechnen" einen Grund', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungStatusSetzen::class, ['id' => $leistung->id, 'nicht_abrechnen' => true])
        ->assertHasErrors();
});

it('nimmt eine nicht abgerechnete Leistung aus dem Soll-Umsatz', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->create(['sales_price_cents' => 1990]);

    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungStatusSetzen::class, [
            'id' => $leistung->id,
            'nicht_abrechnen' => true,
            'nicht_abrechnen_grund' => 'goodwill',
        ])
        ->assertOk();

    expect($leistung->refresh()->countsTowardsRevenue())->toBeFalse();
});

it('filtert die Leistungssuche nach Kunde und Status', function (): void {
    CustomerService::factory()->for($this->kunde)->create(['name' => 'Webhosting']);
    CustomerService::factory()->for($this->kunde)->paused()->create(['name' => 'Pausiertes Paket']);
    CustomerService::factory()->for(Customer::factory()->company())->create(['name' => 'Fremdleistung']);

    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungenSuchen::class, ['kunde_id' => $this->kunde->id, 'status' => 'active'])
        ->assertOk()
        ->assertSee('Webhosting')
        ->assertDontSee('Pausiertes Paket')
        ->assertDontSee('Fremdleistung');
});

it('liest eine Leistung mit ihren geplanten Preisänderungen', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->create(['name' => 'Webhosting']);
    PriceChange::factory()->for($leistung, 'customerService')->create([
        'new_price_cents' => 2490,
        'effective_date' => now()->addMonth()->toDateString(),
        'applied_at' => null,
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungLesen::class, ['id' => $leistung->id])
        ->assertOk()
        ->assertSee('2490');
});

it('entfernt eine Leistung endgültig samt Preisverlauf', function (): void {
    $leistung = CustomerService::factory()->for($this->kunde)->create(['name' => 'Webhosting']);
    PriceChange::factory()->for($leistung, 'customerService')->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(LeistungLoeschen::class, ['id' => $leistung->id, 'bestaetigung' => 'Webhosting'])
        ->assertOk();

    expect(CustomerService::query()->whereKey($leistung->id)->exists())->toBeFalse()
        ->and(PriceChange::query()->where('customer_service_id', $leistung->id)->exists())->toBeFalse();
});
