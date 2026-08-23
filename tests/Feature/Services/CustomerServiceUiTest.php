<?php

use App\Enums\BillingIntervalUnit;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Livewire\Customers\CustomerDetail;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Services\CustomerServiceDetail;
use App\Livewire\Services\CustomerServiceForm;
use App\Livewire\Services\CustomerServices;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('legt eine Kundenleistung ueber das Formular an', function (): void {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceForm::class, ['customer' => $customer])
        ->set('name', 'Hosting Webseite Müller')
        ->set('sales_price', '49,00')
        ->set('purchase_price', '18,00')
        ->set('status', CustomerServiceStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $service = $customer->services()->firstOrFail();

    expect($service->sales_price_cents)->toBe(4900)
        ->and($service->status)->toBe(CustomerServiceStatus::Active);
});

it('uebernimmt die Werte des gewaehlten Katalogartikels als Vorschlag', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Managed Hosting',
        'default_purchase_price_cents' => 1800,
        'default_sales_price_cents' => 5900,
        'default_billing_interval_unit' => BillingIntervalUnit::Month,
        'default_billing_interval_count' => 1,
    ]);
    $product->serviceComponents()->create(['title' => 'Tägliches Backup', 'sort_order' => 0]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceForm::class, ['customer' => $customer])
        ->set('product_id', (string) $product->id)
        ->assertSet('purchase_price', '18,00')
        ->assertSet('sales_price', '59,00')
        ->assertSet('name', 'Managed Hosting')
        ->assertSet('components.0.title', 'Tägliches Backup');
});

it('uebernimmt die Werte der gewaehlten Variante', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create([
        'default_purchase_price_cents' => 1800,
        'default_sales_price_cents' => 5900,
    ]);
    $variant = $product->variants()->create([
        'name' => 'Premium',
        'purchase_price_cents' => 3200,
        'sales_price_cents' => 9900,
        'status' => 'active',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceForm::class, ['customer' => $customer])
        ->set('product_id', (string) $product->id)
        ->set('product_variant_id', (string) $variant->id)
        ->assertSet('purchase_price', '32,00')
        ->assertSet('sales_price', '99,00');
});

it('lehnt ungueltige Geldbetraege ab', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceForm::class, ['customer' => Customer::factory()->create()])
        ->set('name', 'Kaputt')
        ->set('sales_price', 'keine Zahl')
        ->call('save')
        ->assertHasErrors('sales_price');
});

it('verweigert das Bearbeiten einer archivierten Leistung', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->archived()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceForm::class, ['customer' => $customer, 'service' => $service])
        ->assertForbidden();
});

it('verweigert den Zugriff auf eine Leistung eines anderen Kunden', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->assertNotFound();
});

it('kennzeichnet eine Leistung ueber die Detailseite als nicht abzurechnen', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->set('doNotBillReason', DoNotBillReason::OwnService->value)
        ->call('markDoNotBill')
        ->assertHasNoErrors();

    expect($service->fresh()->do_not_bill_reason)->toBe(DoNotBillReason::OwnService);
});

it('wechselt den Status ueber die Detailseite', function (): void {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->planned()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServiceDetail::class, ['customer' => $customer, 'service' => $service])
        ->call('changeStatus', CustomerServiceStatus::Active->value);

    expect($service->fresh()->status)->toBe(CustomerServiceStatus::Active);
});

it('zeigt Leistungen und Kennzahlen im Kundenbereich', function (): void {
    $customer = Customer::factory()->create();

    CustomerService::factory()->for($customer)->create([
        'name' => 'Managed Hosting Müller',
        'purchase_price_cents' => 1800,
        'sales_price_cents' => 5900,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerServices::class, ['customer' => $customer])
        ->assertSee('Managed Hosting Müller')
        ->assertSee('59,00 €')
        ->assertSee('41,00 €');
});

it('blendet archivierte Leistungen im Kundenbereich standardmaessig aus', function (): void {
    $customer = Customer::factory()->create();

    CustomerService::factory()->for($customer)->create(['name' => 'Aktive Leistung']);
    CustomerService::factory()->for($customer)->archived()->create(['name' => 'Alte Leistung']);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerServices::class, ['customer' => $customer]);

    $component->assertSee('Aktive Leistung')->assertDontSee('Alte Leistung');

    $component->set('showArchived', true)->assertSee('Alte Leistung');
});

it('zeigt die Kennzahlen in der Kundenliste', function (): void {
    $customer = Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH', 'short_label' => 'Müller']);

    CustomerService::factory()->for($customer)->create([
        'purchase_price_cents' => 1800,
        'sales_price_cents' => 5900,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->assertSee('59,00 €')
        ->assertSee('708,00 €')
        ->assertSee('18,00 €')
        ->assertSee('41,00 €');
});

it('meldet beim Archivieren eines Kunden mit aktiven Leistungen einen Fehler', function (): void {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $customer])
        ->call('archive')
        ->assertDispatched('archivierung-nicht-moeglich');

    expect($customer->fresh()->isArchived())->toBeFalse();
});
