<?php

use App\Actions\Reporting\CalculatePortalMetrics;
use App\Enums\ProjectStatus;
use App\Livewire\Dashboard\DashboardPage;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

it('zaehlt aktive und archivierte Kunden getrennt', function (): void {
    Customer::factory()->count(3)->create();
    Customer::factory()->count(2)->archived()->create();

    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['activeCustomers'])->toBe(3)
        ->and($metrics['archivedCustomers'])->toBe(2);
});

it('zaehlt Katalogartikel, Ansprechpartner und aktive Kundenleistungen', function (): void {
    Product::factory()->count(4)->create();
    Product::factory()->archived()->create();
    Contact::factory()->count(2)->create();
    Contact::factory()->archived()->create();
    CustomerService::factory()->count(5)->create();
    CustomerService::factory()->paused()->create();

    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['products'])->toBe(4)
        ->and($metrics['activeContacts'])->toBe(2)
        ->and($metrics['activeServices'])->toBe(5);
});

it('berechnet Soll-Umsatz, Kosten und Marge', function (): void {
    // 59 EUR monatlich, Einkauf 18 EUR.
    CustomerService::factory()->create(['purchase_price_cents' => 1800, 'sales_price_cents' => 5900]);
    // 120 EUR jaehrlich entspricht 10 EUR monatlich, Einkauf 48 EUR jaehrlich.
    CustomerService::factory()->yearly()->create(['purchase_price_cents' => 4800, 'sales_price_cents' => 12000]);

    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['monthlyRevenue']->cents)->toBe(6900)
        ->and($metrics['yearlyRevenue']->cents)->toBe(82800)
        ->and($metrics['monthlyCosts']->cents)->toBe(2200)
        ->and($metrics['yearlyCosts']->cents)->toBe(26400)
        ->and($metrics['monthlyMargin']->cents)->toBe(4700)
        ->and($metrics['yearlyMargin']->cents)->toBe(56400)
        ->and($metrics['marginPercentage'])->toBe(68.12);
});

it('laesst pausierte, nicht abzurechnende und einmalige Leistungen aus den Kennzahlen', function (): void {
    CustomerService::factory()->create(['sales_price_cents' => 5900, 'purchase_price_cents' => 0]);
    CustomerService::factory()->paused()->create(['sales_price_cents' => 9900]);
    CustomerService::factory()->doNotBill()->create(['sales_price_cents' => 9900]);
    CustomerService::factory()->oneTime()->create(['sales_price_cents' => 50000]);

    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['monthlyRevenue']->cents)->toBe(5900)
        ->and($metrics['billableServices'])->toBe(1)
        ->and($metrics['doNotBillServices'])->toBe(1)
        ->and($metrics['oneTimeServices'])->toBe(1);
});

it('liefert ohne Umsatz keinen Prozentwert', function (): void {
    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['monthlyRevenue']->cents)->toBe(0)
        ->and($metrics['marginPercentage'])->toBeNull();
});

it('zeigt die Kennzahlen auf dem Dashboard', function (): void {
    Customer::factory()->count(2)->create();
    CustomerService::factory()->create(['purchase_price_cents' => 1800, 'sales_price_cents' => 5900]);

    Livewire::actingAs(User::factory()->create())
        ->test(DashboardPage::class)
        ->assertSee('Aktive Kunden')
        ->assertSee('Umsatz / Monat')
        ->assertSee('59,00 €')
        ->assertSee('708,00 €')
        ->assertSee('41,00 €');
});

it('zeigt den Verlauf der Abrechnung und seine Zusammensetzung', function (): void {
    $hosting = Category::factory()->create(['name' => 'Hosting']);

    CustomerService::factory()->create([
        'category_id' => $hosting->id,
        'sales_price_cents' => 5900,
        'billing_start_date' => now()->subYear()->startOfMonth(),
        'service_start_date' => now()->subYear()->startOfMonth(),
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(DashboardPage::class)
        ->assertSee('Abrechnung je Monat')
        ->assertSee('Woraus sich das zusammensetzt')
        ->assertSee('Hosting')
        // Zwoelf Monatswerte von 59 EUR.
        ->assertSee('708,00 €');
});

it('zeigt weder Bestandsliste noch die Ausnahmen der Kennzahlen', function (): void {
    // Beide Panels hat der Auftraggeber aus der Uebersicht genommen.
    Livewire::actingAs(User::factory()->create())
        ->test(DashboardPage::class)
        ->assertDontSee('Nicht in den Kennzahlen')
        ->assertDontSee('Archivierte Kunden');
});

it('zeigt die Zahl der laufenden Projekte in der Navigation', function (): void {
    Project::factory()->count(3)->create();

    // Geplant und pausiert laufen nicht — der Zaehler nennt, woran gerade
    // gearbeitet wird.
    Project::factory()->planned()->create();
    Project::factory()->create(['status' => ProjectStatus::OnHold]);
    Project::factory()->completed()->create();
    Project::factory()->archived()->create();

    // Der Zaehler haengt am Layout, nicht an der Komponente — deshalb ueber
    // eine echte Anfrage.
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Projekte</span>', escape: false)
        ->assertSeeInOrder([
            'Projekte</span>',
            '<span class="font-mono text-[10px] tabular-nums text-ink-faint">3</span>',
        ], escape: false);
});
