<?php

use App\Actions\Reporting\CalculatePortalMetrics;
use App\Livewire\Dashboard\DashboardPage;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

function metricsLicense(array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'purchase_cost_model' => 'shared_unlimited',
        'purchase_usage_unit' => 'website',
        'purchase_amount_cents' => 29999,
        'purchase_currency' => 'EUR',
        'purchase_interval_unit' => 'year',
        'purchase_interval_count' => 1,
    ], $attributes));
}

it('zaehlt eine gekaufte gemeinsame Lizenz auch ohne Nutzung als Kosten', function (): void {
    metricsLicense();

    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['monthlyCosts']->cents)->toBe(2500)
        ->and($metrics['yearlyCosts']->cents)->toBe(29999)
        ->and($metrics['monthlyMargin']->cents)->toBe(-2500)
        ->and($metrics['costsComplete'])->toBeTrue();
});

it('zaehlt die gesamten Lizenzkosten genau einmal auch bei kostenloser Nutzung', function (): void {
    $product = metricsLicense(['purchase_amount_cents' => 12000]);
    CustomerService::factory()->create(['product_id' => $product->id, 'purchase_price_cents' => 9000, 'sales_price_cents' => 5000]);
    CustomerService::factory()->doNotBill()->create(['product_id' => $product->id, 'purchase_price_cents' => 9000]);

    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['monthlyRevenue']->cents)->toBe(5000)
        ->and($metrics['monthlyCosts']->cents)->toBe(1000)
        ->and($metrics['yearlyCosts']->cents)->toBe(12000)
        ->and($metrics['billableServices'])->toBe(1);
});

it('kennzeichnet fehlende EUR Kosten und zeigt keine definitive Prozentmarge', function (): void {
    $product = metricsLicense(['purchase_currency' => 'USD', 'purchase_eur_amount_cents' => null]);
    CustomerService::factory()->create(['product_id' => $product->id]);

    $metrics = app(CalculatePortalMetrics::class)();

    expect($metrics['costsComplete'])->toBeFalse()
        ->and($metrics['missingCostProducts'])->toBe(1)
        ->and($metrics['marginPercentage'])->toBeNull();

    Livewire::actingAs(User::factory()->create())
        ->test(DashboardPage::class)
        ->assertSee('Kostenbasis unvollständig');
});

it('weist den Lizenzkostenanteil auch einem kostenlos versorgten Kunden zu', function (): void {
    $product = metricsLicense(['purchase_amount_cents' => 12000]);
    $service = CustomerService::factory()->doNotBill()->create(['product_id' => $product->id]);

    expect($service->customer->monthlyRevenue()->cents)->toBe(0)
        ->and($service->customer->monthlyCosts()->cents)->toBe(1000)
        ->and($service->customer->monthlyMargin()->cents)->toBe(-1000);
});
