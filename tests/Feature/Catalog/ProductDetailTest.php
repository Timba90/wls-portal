<?php

use App\Livewire\Catalog\ProductDetail;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ServiceComponent;
use App\Models\User;
use Livewire\Livewire;

it('zeigt die Kundenleistungen, die auf dem Artikel beruhen', function (): void {
    $artikel = Product::factory()->create(['name' => 'Webhosting Standard']);
    $kunde = Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH', 'short_label' => 'Müller']);

    CustomerService::factory()->for($kunde)->for($artikel)->create(['name' => 'Webhosting Müller']);
    CustomerService::factory()->for(Customer::factory())->create(['name' => 'Fremde Leistung']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->assertSee('Müller Elektrotechnik GmbH')
        ->assertDontSee('Fremde Leistung');
});

it('nennt den Leerzustand des Entwurfs, wenn der Artikel nirgends verwendet wird', function (): void {
    $artikel = Product::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->assertSee('Dieser Artikel ist noch keinem Vertrag zugeordnet.');
});

it('liest die Preisentwicklung aus der Aenderungshistorie', function (): void {
    $artikel = Product::factory()->create(['default_sales_price_cents' => 990]);

    $artikel->update(['default_sales_price_cents' => 1490]);

    $verlauf = Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->instance()
        ->priceHistory();

    // Neueste zuerst: die Änderung steht vor den beiden Anlagewerten.
    expect($verlauf->first()['feld'])->toBe('Verkaufspreis')
        ->and($verlauf->first()['alt']->cents)->toBe(990)
        ->and($verlauf->first()['neu']->cents)->toBe(1490);
});

it('beginnt die Preisentwicklung bei der Anlage des Artikels', function (): void {
    $artikel = Product::factory()->create([
        'default_sales_price_cents' => 990,
        'default_purchase_price_cents' => 250,
    ]);

    $verlauf = Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->instance()
        ->priceHistory();

    // Beide Preise stammen aus dem Anlage-Eintrag und haben keinen Vorwert.
    expect($verlauf)->toHaveCount(2)
        ->and($verlauf->pluck('feld')->all())->toBe(['Verkaufspreis', 'Einkaufspreis'])
        ->and($verlauf->every(fn (array $eintrag): bool => $eintrag['alt'] === null))->toBeTrue();
});

it('erfasst auch Aenderungen am Einkaufspreis', function (): void {
    $artikel = Product::factory()->create(['default_purchase_price_cents' => 250]);

    $artikel->update(['default_purchase_price_cents' => 300]);

    $verlauf = Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->instance()
        ->priceHistory();

    expect($verlauf->pluck('feld')->all())->toContain('Einkaufspreis');
});

it('zeigt in der Preisentwicklung den Anlagepreis an', function (): void {
    $artikel = Product::factory()->create(['default_sales_price_cents' => 990]);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->assertSee('9,90')
        ->assertDontSee('Der Listenpreis wurde seit dem Anlegen nicht geändert.');
});

it('zeigt den Leistungsumfang aus den Bestandteilen', function (): void {
    $artikel = Product::factory()->create();

    ServiceComponent::factory()->for($artikel, 'componentable')->create(['title' => '10 GB SSD-Speicher']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->assertSee('10 GB SSD-Speicher');
});

it('zaehlt die Varianten in den Stammdaten und zeigt die Null als Zahl', function (): void {
    $ohne = Product::factory()->create();

    $stammdaten = Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $ohne])
        ->instance()
        ->masterData();

    // "0" ist in PHP falsy — die Ansicht darf daraus keinen Gedankenstrich machen.
    expect($stammdaten['Varianten'])->toBe('0');

    $mit = Product::factory()->create();
    ProductVariant::factory()->count(2)->for($mit)->create();

    $stammdatenMit = Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $mit])
        ->instance()
        ->masterData();

    expect($stammdatenMit['Varianten'])->toBe('2');
});

it('fuehrt die Stammdaten in der Reihenfolge des Entwurfs', function (): void {
    $artikel = Product::factory()->create();

    $stammdaten = Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $artikel])
        ->instance()
        ->masterData();

    expect(array_keys($stammdaten))->toBe([
        'Interner Name', 'Kategorie', 'Unterkategorie', 'Turnus',
        'Einkaufspreis', 'Verkaufspreis', 'Varianten', 'Angelegt', 'Zuletzt geändert',
    ]);
});
