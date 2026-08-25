<?php

use App\Enums\BillingIntervalUnit;
use App\Livewire\Catalog\ProductForm;
use App\Livewire\Services\CustomerServiceForm;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\User;
use App\Support\BillingInterval;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('rechnet einen Jahrespreis auf den Monat um', function (): void {
    expect(BillingInterval::yearly()->convertTo(Money::fromCents(1500), BillingInterval::monthly())->cents)
        ->toBe(125);
});

it('rechnet einen Monatspreis auf das Jahr um', function (): void {
    expect(BillingInterval::monthly()->convertTo(Money::fromCents(125), BillingInterval::yearly())->cents)
        ->toBe(1500);
});

it('rechnet zwischen Quartal und Monat', function (): void {
    $quartal = BillingInterval::make(BillingIntervalUnit::Month, 3);

    expect($quartal->convertTo(Money::fromCents(3000), BillingInterval::monthly())->cents)->toBe(1000)
        ->and(BillingInterval::monthly()->convertTo(Money::fromCents(1000), $quartal)->cents)->toBe(3000);
});

it('rundet kaufmaennisch auf ganze Cent', function (): void {
    // 14,99 EUR im Jahr sind 1,2491… EUR im Monat.
    expect(BillingInterval::yearly()->convertTo(Money::fromCents(1499), BillingInterval::monthly())->cents)
        ->toBe(125);
});

it('laesst einmalige Betraege unveraendert', function (): void {
    // Ein einmaliger Preis bezieht sich auf keinen Zeitraum.
    expect(BillingInterval::once()->convertTo(Money::fromCents(50000), BillingInterval::monthly())->cents)
        ->toBe(50000)
        ->and(BillingInterval::monthly()->convertTo(Money::fromCents(1000), BillingInterval::once())->cents)
        ->toBe(1000);
});

it('rechnet die Preise im Formular um, wenn das Intervall wechselt', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(CustomerServiceForm::class, ['customer' => Customer::factory()->create()])
        ->set('billing_interval_unit', BillingIntervalUnit::Year->value)
        ->set('purchase_price', '12,00')
        ->set('sales_price', '15,00')
        ->set('billing_interval_unit', BillingIntervalUnit::Month->value)
        ->assertSet('purchase_price', '1,00')
        ->assertSet('sales_price', '1,25');
});

it('rechnet auch, wenn nur die Anzahl wechselt', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(CustomerServiceForm::class, ['customer' => Customer::factory()->create()])
        ->set('sales_price', '10,00')
        // Von monatlich auf quartalsweise: derselbe Wert je Quartal.
        ->set('billing_interval_count', 3)
        ->assertSet('sales_price', '30,00');
});

it('rechnet die Bestandteile mit', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(CustomerServiceForm::class, ['customer' => Customer::factory()->create()])
        ->set('billing_interval_unit', BillingIntervalUnit::Year->value)
        ->set('components.0.purchase_price', '12,00')
        ->set('components.0.sales_price', '24,00')
        ->set('billing_interval_unit', BillingIntervalUnit::Month->value)
        ->assertSet('components.0.purchase_price', '1,00')
        ->assertSet('components.0.sales_price', '2,00');
});

it('laesst leere Preisfelder leer', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(CustomerServiceForm::class, ['customer' => Customer::factory()->create()])
        ->set('components.0.purchase_price', '')
        ->set('billing_interval_unit', BillingIntervalUnit::Year->value)
        ->assertSet('components.0.purchase_price', '');
});

it('rechnet beim Bearbeiten einer bestehenden Leistung genauso', function (): void {
    $leistung = CustomerService::factory()->yearly()->create([
        'purchase_price_cents' => 1200,
        'sales_price_cents' => 1500,
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(CustomerServiceForm::class, ['customer' => $leistung->customer, 'service' => $leistung])
        ->assertSet('sales_price', '15,00')
        ->set('billing_interval_unit', BillingIntervalUnit::Month->value)
        ->assertSet('purchase_price', '1,00')
        ->assertSet('sales_price', '1,25');
});

it('rechnet nicht, wenn der Katalogartikel Preis und Intervall gemeinsam setzt', function (): void {
    // Der Artikel liefert beides passend zueinander — hier gibt es nichts
    // umzurechnen, sonst waere der Vorschlag sofort falsch.
    $artikel = Product::factory()->create([
        'default_purchase_price_cents' => 1200,
        'default_sales_price_cents' => 1500,
        'default_billing_interval_unit' => BillingIntervalUnit::Year,
        'default_billing_interval_count' => 1,
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(CustomerServiceForm::class, ['customer' => Customer::factory()->create()])
        ->set('product_id', (string) $artikel->id)
        ->assertSet('billing_interval_unit', BillingIntervalUnit::Year->value)
        ->assertSet('purchase_price', '12,00')
        ->assertSet('sales_price', '15,00');
});

it('rechnet auch die Vorgabepreise eines Katalogartikels um', function (): void {
    // Derselbe Handgriff im Artikelkatalog: ohne Umrechnung wanderte der
    // verzwölffachte Vorgabepreis in jede neu angelegte Leistung.
    Livewire::actingAs($this->benutzer)
        ->test(ProductForm::class)
        ->set('default_billing_interval_unit', BillingIntervalUnit::Year->value)
        ->set('default_purchase_price', '12,00')
        ->set('default_sales_price', '15,00')
        ->set('default_billing_interval_unit', BillingIntervalUnit::Month->value)
        ->assertSet('default_purchase_price', '1,00')
        ->assertSet('default_sales_price', '1,25');
});

it('bricht nicht ab, wenn im Preisfeld etwas Unlesbares steht', function (): void {
    // Wer „ca. 15" tippt und dann das Intervall wechselt, darf keine
    // abgebrochene Anfrage bekommen — die Eingabe bleibt stehen und die
    // Validierung beim Speichern meldet sie.
    Livewire::actingAs($this->benutzer)
        ->test(CustomerServiceForm::class, ['customer' => Customer::factory()->create()])
        ->set('sales_price', 'ca. 15')
        ->set('billing_interval_unit', BillingIntervalUnit::Year->value)
        ->assertSet('sales_price', 'ca. 15')
        ->assertHasNoErrors();
});

it('haelt zwei Intervalle nach Einheit und Anzahl auseinander, nicht nach Beschriftung', function (): void {
    expect(BillingInterval::monthly()->equals(BillingInterval::make(BillingIntervalUnit::Month, 1)))->toBeTrue()
        ->and(BillingInterval::monthly()->equals(BillingInterval::make(BillingIntervalUnit::Month, 3)))->toBeFalse()
        // Zwoelf Monate und ein Jahr sind gleich lang, aber nicht dasselbe
        // Intervall — abgerechnet wird zu verschiedenen Zeitpunkten.
        ->and(BillingInterval::yearly()->equals(BillingInterval::make(BillingIntervalUnit::Month, 12)))->toBeFalse();
});

it('bricht auch im Artikelformular nicht an einer unlesbaren Eingabe ab', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(ProductForm::class)
        ->set('default_sales_price', 'etwa 20')
        ->set('default_billing_interval_unit', BillingIntervalUnit::Year->value)
        ->assertSet('default_sales_price', 'etwa 20')
        ->assertHasNoErrors();
});
