<?php

use App\Actions\Reporting\CalculateBillingForecast;
use App\Actions\Reporting\CalculatePortalMetrics;
use App\Enums\BillingIntervalUnit;
use App\Models\Category;
use App\Models\CustomerService;
use Carbon\CarbonImmutable;

/**
 * Fester Beginn des Fensters, damit die Reihe nicht davon abhaengt, wann der
 * Test laeuft.
 */
function fenster(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-01-15');
}

/**
 * @return array<int, int>
 */
function betraegeJeMonat(array $prognose): array
{
    return array_map(fn (array $monat): int => $monat['amount']->cents, $prognose['months']);
}

it('verteilt eine Jahresleistung nicht auf zwoelf Monate', function (): void {
    CustomerService::factory()->yearly()->create([
        'sales_price_cents' => 120000,
        'service_start_date' => '2025-04-01',
        'billing_start_date' => '2025-04-01',
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    // Januar bis Dezember 2026: nur der April traegt den vollen Betrag.
    expect(betraegeJeMonat($prognose))
        ->toBe([0, 0, 0, 120000, 0, 0, 0, 0, 0, 0, 0, 0])
        ->and($prognose['months'][3]['label'])->toBe('April 2026')
        ->and($prognose['months'][3]['count'])->toBe(1)
        ->and($prognose['total']->cents)->toBe(120000);
});

it('legt eine monatliche Leistung in jeden Monat', function (): void {
    CustomerService::factory()->create([
        'sales_price_cents' => 5900,
        'service_start_date' => '2025-09-01',
        'billing_start_date' => '2025-09-01',
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect(betraegeJeMonat($prognose))->toBe(array_fill(0, 12, 5900))
        ->and($prognose['total']->cents)->toBe(70800);
});

it('trifft mit einer Quartalsleistung vier Monate des Fensters', function (): void {
    CustomerService::factory()->create([
        'billing_interval_unit' => BillingIntervalUnit::Month,
        'billing_interval_count' => 3,
        'sales_price_cents' => 30000,
        'service_start_date' => '2025-02-01',
        'billing_start_date' => '2025-02-01',
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    // Ab Februar 2025 alle drei Monate: im Fenster also Februar, Mai,
    // August und November 2026.
    expect(betraegeJeMonat($prognose))
        ->toBe([0, 30000, 0, 0, 30000, 0, 0, 30000, 0, 0, 30000, 0]);
});

it('faengt bei einem Abrechnungsbeginn in der Zukunft genau dort an', function (): void {
    // Der Anker liegt hinter dem Fensteranfang. Waere der Monatsabstand
    // absolut gerechnet, wuerde die erste Faelligkeit uebersprungen.
    CustomerService::factory()->create([
        'billing_interval_unit' => BillingIntervalUnit::Month,
        'billing_interval_count' => 3,
        'sales_price_cents' => 30000,
        'service_start_date' => '2026-03-01',
        'billing_start_date' => '2026-03-01',
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    // Ab Maerz 2026 alle drei Monate: Maerz, Juni, September, Dezember.
    expect(betraegeJeMonat($prognose))
        ->toBe([0, 0, 30000, 0, 0, 30000, 0, 0, 30000, 0, 0, 30000]);
});

it('laesst eine Leistung aus, deren erste Faelligkeit hinter dem Fenster liegt', function (): void {
    CustomerService::factory()->yearly()->create([
        'sales_price_cents' => 120000,
        'service_start_date' => '2027-06-01',
        'billing_start_date' => '2027-06-01',
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect($prognose['total']->cents)->toBe(0)
        ->and($prognose['unscheduled'])->toBe(0);
});

it('weist eine Leistung ohne Abrechnungsdatum getrennt aus, statt den Monat zu raten', function (): void {
    CustomerService::factory()->yearly()->create([
        'sales_price_cents' => 120000,
        'service_start_date' => null,
        'billing_start_date' => null,
        'first_billing_date' => null,
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect($prognose['unscheduled'])->toBe(1)
        ->and($prognose['total']->cents)->toBe(0);
});

it('braucht fuer eine monatliche Leistung kein Abrechnungsdatum', function (): void {
    // Ein Monatsrhythmus trifft ohnehin jeden Monat — hier ist nichts zu raten.
    CustomerService::factory()->create([
        'sales_price_cents' => 5900,
        'service_start_date' => null,
        'billing_start_date' => null,
        'first_billing_date' => null,
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect($prognose['unscheduled'])->toBe(0)
        ->and($prognose['total']->cents)->toBe(70800);
});

it('laesst pausierte, nicht abzurechnende und einmalige Leistungen aussen vor', function (): void {
    CustomerService::factory()->paused()->create(['sales_price_cents' => 9900]);
    CustomerService::factory()->doNotBill()->create(['sales_price_cents' => 9900]);
    CustomerService::factory()->oneTime()->create(['sales_price_cents' => 50000]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect($prognose['total']->cents)->toBe(0)
        ->and($prognose['unscheduled'])->toBe(0);
});

it('kommt in zwoelf Monaten auf denselben Betrag wie der normalisierte Jahresumsatz', function (): void {
    CustomerService::factory()->create([
        'sales_price_cents' => 5900,
        'billing_start_date' => '2025-01-01',
        'service_start_date' => '2025-01-01',
    ]);

    CustomerService::factory()->yearly()->create([
        'sales_price_cents' => 120000,
        'billing_start_date' => '2025-06-01',
        'service_start_date' => '2025-06-01',
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect($prognose['total']->cents)->toBe(app(CalculatePortalMetrics::class)()['yearlyRevenue']->cents);
});

it('gliedert die Zusammensetzung absteigend und ohne Kategorie als eigene Zeile', function (): void {
    $hosting = Category::factory()->create(['name' => 'Hosting']);

    CustomerService::factory()->create([
        'category_id' => $hosting->id,
        'sales_price_cents' => 1000,
        'billing_start_date' => '2025-01-01',
        'service_start_date' => '2025-01-01',
    ]);

    CustomerService::factory()->create([
        'category_id' => null,
        'sales_price_cents' => 2500,
        'billing_start_date' => '2025-01-01',
        'service_start_date' => '2025-01-01',
    ]);

    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect($prognose['composition'])->toHaveCount(2)
        ->and($prognose['composition'][0]['label'])->toBe('Ohne Kategorie')
        ->and($prognose['composition'][0]['amount']->cents)->toBe(30000)
        ->and($prognose['composition'][1]['label'])->toBe('Hosting')
        ->and($prognose['composition'][1]['amount']->cents)->toBe(12000);

    $summe = array_sum(array_map(
        fn (array $anteil): int => $anteil['amount']->cents,
        $prognose['composition'],
    ));

    expect($summe)->toBe($prognose['total']->cents);
});

it('liefert ohne abzurechnende Leistung ein leeres, aber vollstaendiges Fenster', function (): void {
    $prognose = app(CalculateBillingForecast::class)(fenster());

    expect($prognose['months'])->toHaveCount(12)
        ->and($prognose['total']->cents)->toBe(0)
        ->and($prognose['peak']->cents)->toBe(0)
        ->and($prognose['composition'])->toBe([]);
});

it('listet dieselben Leistungen auf, die die Grafik nicht einplanen kann', function (): void {
    // Ohne Datum und mit Jahresrhythmus: nicht planbar.
    $ohneDatum = CustomerService::factory()->yearly()->create([
        'name' => 'Domain ohne Datum',
        'service_start_date' => null,
        'billing_start_date' => null,
        'first_billing_date' => null,
    ]);

    // Monatlich ohne Datum ist planbar — trifft ohnehin jeden Monat.
    CustomerService::factory()->create([
        'name' => 'Monatlich ohne Datum',
        'service_start_date' => null,
        'billing_start_date' => null,
        'first_billing_date' => null,
    ]);

    // Mit Datum ist planbar.
    CustomerService::factory()->yearly()->create([
        'name' => 'Domain mit Datum',
        'billing_start_date' => '2025-04-01',
        'service_start_date' => '2025-04-01',
    ]);

    $gefunden = CustomerService::query()->withoutBillingSchedule()->pluck('name')->all();

    expect($gefunden)->toBe(['Domain ohne Datum'])
        // Die Zahl unter der Grafik und die Liste müssen dieselbe Menge meinen.
        ->and(app(CalculateBillingForecast::class)(fenster())['unscheduled'])->toBe(count($gefunden));
});

it('zaehlt eine bewusst nicht abgerechnete Leistung nicht als fehlend', function (): void {
    CustomerService::factory()->yearly()->doNotBill()->create([
        'service_start_date' => null,
        'billing_start_date' => null,
        'first_billing_date' => null,
    ]);

    expect(CustomerService::query()->withoutBillingSchedule()->count())->toBe(0);
});
