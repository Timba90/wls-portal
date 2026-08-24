<?php

use App\Actions\Services\CompareWithCatalog;
use App\Actions\Services\CreateCustomerService;
use App\Actions\Services\FindServicesWithCatalogChanges;
use App\Actions\Services\ResolveCatalogChange;
use App\Enums\BillingIntervalUnit;
use App\Enums\PriceType;
use App\Exceptions\ReadOnlyRecordException;
use App\Livewire\Catalog\ProductDetail;
use App\Livewire\Services\CustomerServiceDetail;
use App\Livewire\Services\ServiceOverview;
use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Services\KatalogabgleichLesen;
use App\Mcp\Tools\Services\KatalogaenderungEntscheiden;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PriceChange;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

/**
 * Legt eine Kundenleistung an, die den Katalogwerten exakt entspricht.
 */
function leistungAusKatalog(array $katalog = [], array $abweichend = []): CustomerService
{
    $product = Product::factory()->create([
        'name' => 'Managed Hosting',
        'default_purchase_price_cents' => 1800,
        'default_sales_price_cents' => 5900,
        'default_billing_interval_unit' => BillingIntervalUnit::Month->value,
        'default_billing_interval_count' => 1,
        ...$katalog,
    ]);

    return app(CreateCustomerService::class)(Customer::factory()->create(), [
        'product_id' => $product->id,
        'name' => 'Hosting Webseite Müller',
        'purchase_price' => '18,00',
        'sales_price' => '59,00',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
        'billing_interval_count' => 1,
        ...$abweichend,
    ]);
}

it('meldet nichts, solange der Katalog unveraendert ist', function (): void {
    $leistung = leistungAusKatalog();

    expect(app(CompareWithCatalog::class)($leistung))->toBe([])
        ->and(app(CompareWithCatalog::class)->hasOpenChanges($leistung))->toBeFalse();
});

it('meldet nichts fuer eine frei erfasste Leistung', function (): void {
    $leistung = CustomerService::factory()->create(['product_id' => null, 'catalog_snapshot' => null]);

    // Eine Leistung ohne Katalogherkunft weicht von nichts ab.
    expect(app(CompareWithCatalog::class)($leistung))->toBe([]);
});

it('erkennt eine Preisaenderung im Katalog', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);

    $zeilen = collect(app(CompareWithCatalog::class)($leistung->refresh()));
    $verkauf = $zeilen->firstWhere('feld', 'sales_price_cents');

    expect($verkauf['stand'])->toBe('59,00 €')
        ->and($verkauf['katalog'])->toBe('69,00 €')
        ->and($verkauf['leistung'])->toBe('59,00 €')
        ->and($verkauf['katalogGeaendert'])->toBeTrue()
        ->and($verkauf['kundeWeichtAb'])->toBeFalse();
});

it('trennt eine Katalogaenderung von einer bewussten Kundenabweichung', function (): void {
    // Der Kunde zahlt von Anfang an weniger, der Katalog aendert sich nicht.
    $leistung = leistungAusKatalog(abweichend: ['sales_price' => '49,00']);

    $verkauf = collect(app(CompareWithCatalog::class)($leistung))->firstWhere('feld', 'sales_price_cents');

    expect($verkauf['kundeWeichtAb'])->toBeTrue()
        ->and($verkauf['katalogGeaendert'])->toBeFalse()
        ->and($verkauf['uebernehmbar'])->toBeFalse()
        ->and(app(CompareWithCatalog::class)->hasOpenChanges($leistung))->toBeFalse();
});

it('zeigt bei uebernommener Aenderung alle drei Staende nebeneinander', function (): void {
    $leistung = leistungAusKatalog(abweichend: ['sales_price' => '49,00']);
    $leistung->product->update(['default_sales_price_cents' => 6900]);

    $verkauf = collect(app(CompareWithCatalog::class)($leistung->refresh()))->firstWhere('feld', 'sales_price_cents');

    expect($verkauf['stand'])->toBe('59,00 €')
        ->and($verkauf['katalog'])->toBe('69,00 €')
        ->and($verkauf['leistung'])->toBe('49,00 €')
        ->and($verkauf['katalogGeaendert'])->toBeTrue()
        ->and($verkauf['kundeWeichtAb'])->toBeTrue();
});

it('uebernimmt einen Katalogpreis ueber den Preisverlauf', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);

    app(ResolveCatalogChange::class)($leistung->refresh(), 'sales_price_cents', adopt: true, user: User::factory()->create());

    $eintrag = PriceChange::query()->where('customer_service_id', $leistung->id)
        ->where('price_type', PriceType::Sales)->latest('id')->first();

    expect($leistung->refresh()->sales_price_cents)->toBe(6900)
        // Der Preis darf nicht still ueberschrieben werden.
        ->and($eintrag)->not->toBeNull()
        ->and($eintrag->old_price_cents)->toBe(5900)
        ->and($eintrag->new_price_cents)->toBe(6900)
        ->and($eintrag->applied_at)->not->toBeNull();
});

it('meldet eine entschiedene Aenderung nicht erneut', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);

    app(ResolveCatalogChange::class)($leistung->refresh(), 'sales_price_cents', adopt: true);

    expect(app(CompareWithCatalog::class)->hasOpenChanges($leistung->refresh()))->toBeFalse();
});

it('behaelt den Kundenpreis und meldet die Aenderung trotzdem nicht erneut', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);

    app(ResolveCatalogChange::class)($leistung->refresh(), 'sales_price_cents', adopt: false);

    $leistung->refresh();

    expect($leistung->sales_price_cents)->toBe(5900)
        ->and(app(CompareWithCatalog::class)->hasOpenChanges($leistung))->toBeFalse()
        // Die bewusste Abweichung bleibt sichtbar, sie ist nur nicht mehr offen.
        ->and(collect(app(CompareWithCatalog::class)($leistung))->firstWhere('feld', 'sales_price_cents')['kundeWeichtAb'])
        ->toBeTrue();
});

it('laesst andere offene Aenderungen beim Entscheiden unangetastet', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update([
        'default_sales_price_cents' => 6900,
        'default_purchase_price_cents' => 2200,
    ]);

    app(ResolveCatalogChange::class)($leistung->refresh(), 'sales_price_cents', adopt: true);

    $zeilen = collect(app(CompareWithCatalog::class)($leistung->refresh()));

    // Wer über den Verkaufspreis entscheidet, hat nicht über den Einkauf entschieden.
    expect($zeilen->firstWhere('feld', 'purchase_price_cents')['katalogGeaendert'])->toBeTrue()
        ->and($zeilen->firstWhere('feld', 'sales_price_cents'))->toBeNull();
});

it('uebernimmt ein geaendertes Abrechnungsintervall', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update([
        'default_billing_interval_unit' => BillingIntervalUnit::Year->value,
        'default_billing_interval_count' => 1,
    ]);

    app(ResolveCatalogChange::class)($leistung->refresh(), 'billing_interval', adopt: true);

    expect($leistung->refresh()->billingInterval()->unit)->toBe(BillingIntervalUnit::Year);
});

it('uebernimmt eine geaenderte Kategorie', function (): void {
    $leistung = leistungAusKatalog();
    $neue = Category::factory()->create(['name' => 'Infrastruktur']);
    $leistung->product->update(['category_id' => $neue->id]);

    app(ResolveCatalogChange::class)($leistung->refresh(), 'category', adopt: true);

    expect($leistung->refresh()->category_id)->toBe($neue->id);
});

it('nennt eine umbenannte Katalogbezeichnung, bietet sie aber nicht zur Uebernahme an', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['name' => 'Managed Hosting Pro']);

    $zeile = collect(app(CompareWithCatalog::class)($leistung->refresh()))->firstWhere('feld', 'product_name');

    // Die Leistung trägt bewusst einen eigenen Namen.
    expect($zeile['katalog'])->toBe('Managed Hosting Pro')
        ->and($zeile['leistung'])->toBe('Hosting Webseite Müller')
        ->and($zeile['uebernehmbar'])->toBeFalse();
});

it('schuetzt archivierte Leistungen vor dem Uebernehmen', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);
    $leistung->forceFill(['status' => 'archived', 'archived_at' => now()])->save();

    expect(fn () => app(ResolveCatalogChange::class)($leistung->refresh(), 'sales_price_cents', adopt: true))
        ->toThrow(ReadOnlyRecordException::class);
});

it('zeigt den Reiter Katalog nur bei einer Leistung aus dem Katalog', function (): void {
    $ausKatalog = leistungAusKatalog();
    $frei = CustomerService::factory()->create(['product_id' => null, 'catalog_snapshot' => null]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $ausKatalog->customer, 'service' => $ausKatalog])
        ->assertSee('Katalog');

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $frei->customer, 'service' => $frei])
        ->assertDontSee('Katalogabgleich');
});

it('nennt die Zahl der offenen Katalogaenderungen', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update([
        'default_sales_price_cents' => 6900,
        'default_purchase_price_cents' => 2200,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $leistung->customer, 'service' => $leistung->refresh()])
        ->assertSet('tab', 'preise')
        ->call('$set', 'tab', 'katalog')
        ->assertSee('Katalog geändert')
        ->assertSee('69,00 €');

    expect(Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $leistung->customer, 'service' => $leistung])
        ->instance()->openCatalogChangeCount())->toBe(2);
});

it('uebernimmt eine Katalogaenderung ueber die Oberflaeche', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $leistung->customer, 'service' => $leistung->refresh()])
        ->call('resolveCatalogChange', 'sales_price_cents', true)
        ->assertDispatched('katalog-uebernommen');

    expect($leistung->refresh()->sales_price_cents)->toBe(6900);
});

it('uebernimmt alle offenen Aenderungen auf einmal', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update([
        'default_sales_price_cents' => 6900,
        'default_purchase_price_cents' => 2200,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $leistung->customer, 'service' => $leistung->refresh()])
        ->call('adoptAllCatalogChanges');

    $leistung->refresh();

    expect($leistung->sales_price_cents)->toBe(6900)
        ->and($leistung->purchase_price_cents)->toBe(2200)
        ->and(app(CompareWithCatalog::class)->hasOpenChanges($leistung))->toBeFalse();
});

it('weist in der Leistungsuebersicht auf geaenderte Katalogpositionen hin', function (): void {
    $betroffen = leistungAusKatalog();
    $betroffen->product->update(['default_sales_price_cents' => 6900]);

    leistungAusKatalog();

    Livewire::actingAs(User::factory()->create())
        ->test(ServiceOverview::class)
        ->assertSee('Bei 1 Leistung hat sich der Katalog seither geändert');
});

it('filtert die Uebersicht auf die betroffenen Leistungen', function (): void {
    // Die Übersicht zeigt voreingestellt nur aktive Leistungen.
    $betroffen = leistungAusKatalog(abweichend: ['name' => 'Betroffene Leistung', 'status' => 'active']);
    $betroffen->product->update(['default_sales_price_cents' => 6900]);

    leistungAusKatalog(abweichend: ['name' => 'Unberuehrte Leistung', 'status' => 'active']);

    Livewire::actingAs(User::factory()->create())
        ->test(ServiceOverview::class)
        ->assertSee('Unberuehrte Leistung')
        ->call('toggleCatalogFilter')
        ->assertSee('Betroffene Leistung')
        ->assertDontSee('Unberuehrte Leistung');
});

it('zaehlt archivierte Leistungen nicht als offen', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);
    $leistung->forceFill(['status' => 'archived', 'archived_at' => now()])->save();

    // Eine archivierte Leistung ist schreibgeschützt; es gibt nichts zu entscheiden.
    expect(app(FindServicesWithCatalogChanges::class)())->toBe([]);
});

it('markiert im Artikeldetail die Leistungen mit altem Katalogstand', function (): void {
    $leistung = leistungAusKatalog(abweichend: ['status' => 'active']);
    $artikel = $leistung->product;

    Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->assertDontSee('Katalog geändert');

    $artikel->update(['default_sales_price_cents' => 6900]);

    // Wer gerade den Listenpreis erhöht hat, sieht hier sofort, wen es betrifft.
    Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel->refresh()])
        ->assertSee('Katalog geändert');
});

it('nennt ueber MCP die betroffenen Leistungen', function (): void {
    $betroffen = leistungAusKatalog(abweichend: ['name' => 'Betroffene Leistung']);
    $betroffen->product->update(['default_sales_price_cents' => 6900]);

    leistungAusKatalog(abweichend: ['name' => 'Unberuehrte Leistung']);

    PortalServer::actingAs(User::factory()->create())
        ->tool(KatalogabgleichLesen::class, [])
        ->assertOk()
        ->assertSee('Betroffene Leistung')
        ->assertDontSee('Unberuehrte Leistung');
});

it('entscheidet ueber MCP alle offenen Aenderungen auf einmal', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update([
        'default_sales_price_cents' => 6900,
        'default_purchase_price_cents' => 2200,
    ]);

    PortalServer::actingAs(User::factory()->create())
        ->tool(KatalogaenderungEntscheiden::class, [
            'id' => $leistung->id,
            'entscheidung' => 'uebernehmen',
        ])
        ->assertOk();

    $leistung->refresh();

    expect($leistung->sales_price_cents)->toBe(6900)
        ->and($leistung->purchase_price_cents)->toBe(2200);
});

it('behaelt ueber MCP den Kundenwert', function (): void {
    $leistung = leistungAusKatalog();
    $leistung->product->update(['default_sales_price_cents' => 6900]);

    PortalServer::actingAs(User::factory()->create())
        ->tool(KatalogaenderungEntscheiden::class, [
            'id' => $leistung->id,
            'entscheidung' => 'behalten',
            'feld' => 'sales_price_cents',
        ])
        ->assertOk();

    $leistung->refresh();

    expect($leistung->sales_price_cents)->toBe(5900)
        ->and(app(CompareWithCatalog::class)->hasOpenChanges($leistung))->toBeFalse();
});
