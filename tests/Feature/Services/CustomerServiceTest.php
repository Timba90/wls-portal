<?php

use App\Actions\Customers\ArchiveCustomer;
use App\Actions\Services\ArchiveCustomerService;
use App\Actions\Services\ChangeCustomerServiceStatus;
use App\Actions\Services\CreateCustomerService;
use App\Actions\Services\RestoreCustomerService;
use App\Actions\Services\SetDoNotBill;
use App\Actions\Services\UpdateCustomerService;
use App\Enums\BillingIntervalUnit;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Exceptions\ArchivingNotPossibleException;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Tag;

it('weist einem Kunden einen Katalogartikel zu und behaelt die Herkunft', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Managed Hosting',
        'default_purchase_price_cents' => 1800,
        'default_sales_price_cents' => 5900,
    ]);

    $service = app(CreateCustomerService::class)($customer, [
        'product_id' => $product->id,
        'name' => 'Hosting Webseite Müller',
        'purchase_price' => '18,00',
        'sales_price' => '59,00',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
        'billing_interval_count' => 1,
    ]);

    expect($service->isFromCatalog())->toBeTrue()
        ->and($service->product_id)->toBe($product->id)
        ->and($service->catalog_snapshot)->toHaveKey('sales_price_cents')
        ->and($service->catalog_snapshot['sales_price_cents'])->toBe(5900)
        ->and($service->catalogDeviations())->toBe([]);
});

it('erlaubt einen vom Katalog abweichenden Preis und macht ihn nachvollziehbar', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create([
        'default_purchase_price_cents' => 1800,
        'default_sales_price_cents' => 5900,
    ]);

    $service = app(CreateCustomerService::class)($customer, [
        'product_id' => $product->id,
        'name' => 'Hosting Webseite Müller',
        'purchase_price' => '18,00',
        // Der Kunde zahlt 49 statt 59 EUR.
        'sales_price' => '49,00',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
        'billing_interval_count' => 1,
    ]);

    $abweichungen = $service->catalogDeviations();

    expect($service->sales_price_cents)->toBe(4900)
        ->and($abweichungen)->toHaveKey('Verkaufspreis')
        ->and($abweichungen['Verkaufspreis']['katalog'])->toBe('59,00 €')
        ->and($abweichungen['Verkaufspreis']['kunde'])->toBe('49,00 €');
});

it('legt eine vollstaendig individuelle Leistung ohne Katalogartikel an', function (): void {
    $service = app(CreateCustomerService::class)(Customer::factory()->create(), [
        'name' => 'Individuelle Betreuung',
        'purchase_price' => '0,00',
        'sales_price' => '145,00',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
        'billing_interval_count' => 3,
    ]);

    expect($service->isFromCatalog())->toBeFalse()
        ->and($service->product_id)->toBeNull()
        ->and($service->catalog_snapshot)->toBeNull()
        ->and($service->catalogDeviations())->toBe([])
        ->and($service->billingInterval()->label())->toBe('quartalsweise');
});

it('berechnet Marge, Deckungsbeitrag und Prozentwert', function (): void {
    $service = CustomerService::factory()->create([
        'purchase_price_cents' => 1800,
        'sales_price_cents' => 5900,
    ]);

    expect($service->margin()->cents)->toBe(4100)
        ->and($service->margin()->format())->toBe('41,00 €')
        ->and($service->marginPercentage())->toBe(69.49);
});

it('weist eine negative Marge aus, wenn der Einkauf teurer ist', function (): void {
    $service = CustomerService::factory()->create([
        'purchase_price_cents' => 5900,
        'sales_price_cents' => 4900,
    ]);

    expect($service->margin()->cents)->toBe(-1000)
        ->and($service->margin()->isNegative())->toBeTrue()
        ->and($service->marginPercentage())->toBe(-20.41);
});

it('liefert ohne Verkaufspreis keinen Prozentwert', function (): void {
    $service = CustomerService::factory()->create(['purchase_price_cents' => 0, 'sales_price_cents' => 0]);

    expect($service->marginPercentage())->toBeNull();
});

it('normalisiert Betraege auf Monats- und Jahreswerte', function (): void {
    $jaehrlich = CustomerService::factory()->yearly()->create([
        'purchase_price_cents' => 4800,
        'sales_price_cents' => 12000,
    ]);

    expect($jaehrlich->monthlyRevenue()->cents)->toBe(1000)
        ->and($jaehrlich->yearlyRevenue()->cents)->toBe(12000)
        ->and($jaehrlich->monthlyCosts()->cents)->toBe(400)
        ->and($jaehrlich->monthlyMargin()->cents)->toBe(600);
});

it('laesst einmalige Leistungen nicht in wiederkehrende Kennzahlen einfliessen', function (): void {
    $einmalig = CustomerService::factory()->oneTime()->create(['sales_price_cents' => 50000]);

    expect($einmalig->billing_interval_count)->toBeNull()
        ->and($einmalig->monthlyRevenue()->cents)->toBe(0)
        ->and($einmalig->yearlyRevenue()->cents)->toBe(0)
        ->and($einmalig->countsTowardsRevenue())->toBeFalse();
});

it('kennzeichnet eine Leistung als bewusst nicht abzurechnen', function (): void {
    $service = CustomerService::factory()->create();

    app(SetDoNotBill::class)->mark($service, DoNotBillReason::Goodwill);

    expect($service->do_not_bill)->toBeTrue()
        ->and($service->do_not_bill_reason)->toBe(DoNotBillReason::Goodwill)
        ->and($service->do_not_bill_since)->not->toBeNull()
        ->and($service->do_not_bill_released_at)->toBeNull()
        ->and($service->countsTowardsRevenue())->toBeFalse();
});

it('haelt fest, ab wann nach dem Entfernen wieder normal betrachtet wird', function (): void {
    $service = CustomerService::factory()->doNotBill()->create();

    app(SetDoNotBill::class)->release($service);

    expect($service->do_not_bill)->toBeFalse()
        ->and($service->do_not_bill_reason)->toBeNull()
        ->and($service->do_not_bill_released_at)->not->toBeNull()
        // Der Zeitpunkt des Setzens bleibt erhalten, es gibt keine rueckwirkende
        // Nachberechnung.
        ->and($service->do_not_bill_since)->not->toBeNull()
        ->and($service->countsTowardsRevenue())->toBeTrue();
});

it('wechselt den Status einer Leistung', function (): void {
    $service = CustomerService::factory()->planned()->create();

    app(ChangeCustomerServiceStatus::class)($service, CustomerServiceStatus::Active);
    expect($service->fresh()->status)->toBe(CustomerServiceStatus::Active);

    app(ChangeCustomerServiceStatus::class)($service, CustomerServiceStatus::Paused);
    expect($service->fresh()->status)->toBe(CustomerServiceStatus::Paused)
        ->and($service->fresh()->countsTowardsRevenue())->toBeFalse();
});

it('setzt den Status archiviert nicht ueber den Statuswechsel', function (): void {
    $service = CustomerService::factory()->create();

    expect(fn () => app(ChangeCustomerServiceStatus::class)($service, CustomerServiceStatus::Archived))
        ->toThrow(ReadOnlyRecordException::class);
});

it('macht eine archivierte Leistung schreibgeschuetzt', function (): void {
    $service = CustomerService::factory()->create(['name' => 'Ursprünglicher Name']);

    app(ArchiveCustomerService::class)($service);

    expect($service->isArchived())->toBeTrue()
        ->and($service->isEditable())->toBeFalse();

    expect(fn () => $service->update(['name' => 'Neuer Name']))
        ->toThrow(ReadOnlyRecordException::class);

    expect(fn () => app(UpdateCustomerService::class)($service, [
        'name' => 'Neuer Name',
        'billing_interval_unit' => BillingIntervalUnit::Month->value,
    ]))->toThrow(ReadOnlyRecordException::class);

    expect($service->fresh()->name)->toBe('Ursprünglicher Name');
});

it('verhindert auch das Kennzeichnen archivierter Leistungen', function (): void {
    $service = CustomerService::factory()->create();

    app(ArchiveCustomerService::class)($service);

    expect(fn () => app(SetDoNotBill::class)->mark($service, DoNotBillReason::Included))
        ->toThrow(ReadOnlyRecordException::class);
});

it('hebt die Archivierung einer Leistung wieder auf', function (): void {
    $service = CustomerService::factory()->create();

    app(ArchiveCustomerService::class)($service);
    app(RestoreCustomerService::class)($service);

    expect($service->isArchived())->toBeFalse()
        ->and($service->status)->toBe(CustomerServiceStatus::Ended)
        ->and($service->archived_at)->toBeNull();
});

it('archiviert einen Kunden mit aktiver Leistung nicht', function (): void {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->create();

    expect(fn () => app(ArchiveCustomer::class)($customer))
        ->toThrow(ArchivingNotPossibleException::class);

    expect($customer->fresh()->isArchived())->toBeFalse();
});

it('archiviert einen Kunden ohne aktive Leistungen', function (): void {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->ended()->create();
    CustomerService::factory()->for($customer)->archived()->create();

    app(ArchiveCustomer::class)($customer);

    expect($customer->fresh()->isArchived())->toBeTrue();
});

it('summiert die Kennzahlen eines Kunden nur ueber abrechenbare Leistungen', function (): void {
    $customer = Customer::factory()->create();

    // Zaehlt: 59 EUR monatlich.
    CustomerService::factory()->for($customer)->create([
        'purchase_price_cents' => 1800, 'sales_price_cents' => 5900,
    ]);
    // Zaehlt: 120 EUR jaehrlich entspricht 10 EUR monatlich.
    CustomerService::factory()->for($customer)->yearly()->create([
        'purchase_price_cents' => 4800, 'sales_price_cents' => 12000,
    ]);
    // Zaehlt nicht: pausiert.
    CustomerService::factory()->for($customer)->paused()->create(['sales_price_cents' => 9900]);
    // Zaehlt nicht: bewusst nicht abrechnen.
    CustomerService::factory()->for($customer)->doNotBill()->create(['sales_price_cents' => 9900]);
    // Zaehlt nicht: einmalig.
    CustomerService::factory()->for($customer)->oneTime()->create(['sales_price_cents' => 50000]);

    $customer->load(['services' => fn ($query) => $query->billable()]);

    expect($customer->monthlyRevenue()->cents)->toBe(6900)
        ->and($customer->yearlyRevenue()->cents)->toBe(82800)
        ->and($customer->monthlyCosts()->cents)->toBe(2200)
        ->and($customer->monthlyMargin()->cents)->toBe(4700);
});

it('uebernimmt Tags und Leistungsbestandteile', function (): void {
    $tag = Tag::factory()->create(['name' => 'Wartungsvertrag']);

    $service = app(CreateCustomerService::class)(
        Customer::factory()->create(),
        [
            'name' => 'Managed Website',
            'purchase_price' => '0,00',
            'sales_price' => '99,00',
            'billing_interval_unit' => BillingIntervalUnit::Month->value,
            'billing_interval_count' => 1,
        ],
        tags: [$tag->id],
        components: [
            ['title' => 'Hosting'],
            ['title' => 'Tägliches Backup'],
        ],
    );

    expect($service->tags->pluck('name')->all())->toBe(['Wartungsvertrag'])
        ->and($service->serviceComponents->pluck('title')->all())->toBe(['Hosting', 'Tägliches Backup']);
});
