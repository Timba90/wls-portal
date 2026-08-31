<?php

use App\Actions\Registrar\AssignInventory;
use App\Enums\BillingIntervalUnit;
use App\Livewire\Registrar\DomainList;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Livewire;

/**
 * Der Knopf „Leistung anlegen & zuordnen" im Zuordnungsdialog.
 *
 * Er ist der Normalfall der Zuordnungsarbeit: eine importierte Domain,
 * die noch nicht abgerechnet wird, bekommt eine Leistung zum Katalogpreis
 * — ohne den Umweg über die Kundenverwaltung.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('legt eine monatliche Domain-Leistung zum Katalogpreis an und verknüpft sie', function (): void {
    $kunde = Customer::factory()->create();
    $domain = Domain::factory()->create(['name' => 'beispiel.de']);

    // Der Katalogartikel führt den Preis jährlich — importierte Leistungen
    // normalisieren ihn auf den Monat, der Knopf macht es genauso.
    Product::factory()->create([
        'name' => 'Domain .de',
        'default_sales_price_cents' => 1500,
        'default_purchase_price_cents' => 252,
        'default_billing_interval_unit' => BillingIntervalUnit::Year,
    ]);

    /** @var DomainList $komponente */
    $komponente = Livewire::withQueryParams([])
        ->test(DomainList::class)
        ->call('startAssignment', $domain->id)
        ->set('assignmentCustomerId', (string) $kunde->id)
        ->call('createServiceAndAssign', app(AssignInventory::class));

    $leistung = $kunde->services()->where('name', 'Domain beispiel.de')->first();

    expect($leistung)->not->toBeNull()
        ->and($leistung->billing_interval_unit->value)->toBe('month')
        ->and($leistung->sales_price_cents)->toBe(125)
        ->and($leistung->purchase_price_cents)->toBe(21)
        ->and($leistung->status->value)->toBe('active')
        ->and($domain->refresh()->customer_id)->toBe($kunde->id)
        ->and($domain->customer_service_id)->toBe($leistung->id);
});

it('verlangt einen Kunden, bevor eine Leistung angelegt wird', function (): void {
    $domain = Domain::factory()->create(['name' => 'ohne-kunde.de']);

    Livewire::test(DomainList::class)
        ->call('startAssignment', $domain->id)
        ->call('createServiceAndAssign', app(AssignInventory::class));

    expect($domain->refresh()->customer_id)->toBeNull()
        ->and(CustomerService::query()->where('name', 'Domain ohne-kunde.de')->exists())->toBeFalse();
});

it('übersteht eine fehlende TLD im Katalog mit einer preislosen Leistung', function (): void {
    $kunde = Customer::factory()->create();
    $domain = Domain::factory()->create(['name' => 'exotisch.example']);

    // Kein Artikel „Domain .example" im Katalog — die Leistung entsteht
    // trotzdem, der Preis wird nachträglich gepflegt.
    Livewire::test(DomainList::class)
        ->call('startAssignment', $domain->id)
        ->set('assignmentCustomerId', (string) $kunde->id)
        ->call('createServiceAndAssign', app(AssignInventory::class));

    $leistung = $kunde->services()->where('name', 'Domain exotisch.example')->first();

    expect($leistung)->not->toBeNull()
        ->and($leistung->sales_price_cents)->toBe(0)
        ->and($domain->refresh()->customer_service_id)->toBe($leistung->id);
});
