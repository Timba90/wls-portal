<?php

use App\Actions\Catalog\SaveProduct;
use App\Actions\Services\CreateCustomerService;
use App\Actions\Services\UpdateCustomerService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function licenseProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'purchase_cost_model' => 'shared_pack', 'purchase_usage_unit' => 'user',
        'purchase_amount_cents' => 10001, 'purchase_currency' => 'EUR',
        'purchase_interval_unit' => 'year', 'purchase_interval_count' => 1,
        'purchase_units_per_pack' => 5,
    ], $attributes));
}

it('allocates package totals exactly using active usage including non billed services', function () {
    $product = licenseProduct();
    $first = CustomerService::factory()->for($product)->create(['usage_quantity' => 3, 'purchase_price_cents' => 777]);
    $second = CustomerService::factory()->for($product)->doNotBill()->create(['usage_quantity' => 4]);
    CustomerService::factory()->for($product)->paused()->create(['usage_quantity' => 100]);
    $summary = $product->licenseCostSummary();
    expect($summary['usage_units'])->toBe(7)->and($summary['packages'])->toBe(2)
        ->and($summary['amount_cents'])->toBe(20002)->and($summary['monthly_cost_cents'])->toBe(1667)
        ->and(array_sum(array_column($summary['allocations'], 'yearly_cents')))->toBe(20002)
        ->and(array_sum(array_column($summary['allocations'], 'monthly_cents')))->toBe(1667)
        ->and($first->monthlyCosts()->cents)->toBe($summary['allocations'][$first->id]['monthly_cents'])
        ->and($first->yearlyCosts()->cents)->toBe($summary['allocations'][$first->id]['yearly_cents'])
        ->and($first->fresh()->purchase_price_cents)->toBe(777);
});

it('keeps one shared package unallocated with no active services', function () {
    $summary = licenseProduct()->licenseCostSummary();
    expect($summary['packages'])->toBe(1)->and($summary['unallocated_yearly_cents'])->toBe(10001)
        ->and($summary['unallocated_monthly_cents'])->toBe(833)->and($summary['allocations'])->toBe([]);
});

it('counts customers once and splits each customer share across its services', function () {
    $product = licenseProduct(['purchase_cost_model' => 'per_unit', 'purchase_usage_unit' => 'customer', 'purchase_amount_cents' => 12000]);
    $customer = Customer::factory()->create();
    $a = CustomerService::factory()->for($product)->for($customer)->create(['usage_quantity' => 99]);
    $b = CustomerService::factory()->for($product)->for($customer)->create();
    $c = CustomerService::factory()->for($product)->create();
    $summary = $product->licenseCostSummary();
    expect($summary['usage_units'])->toBe(2)->and($summary['yearly_cost_cents'])->toBe(24000)
        ->and($summary['allocations'][$a->id]['yearly_cents'])->toBe(6000)
        ->and($summary['allocations'][$b->id]['yearly_cents'])->toBe(6000)
        ->and($summary['allocations'][$c->id]['yearly_cents'])->toBe(12000);
});

it('marks missing actual EUR costs and uses independent purchase and sales periods', function () {
    $product = licenseProduct(['purchase_cost_model' => 'shared_unlimited', 'purchase_currency' => 'USD', 'purchase_eur_amount_cents' => null]);
    $service = CustomerService::factory()->for($product)->create(['billing_interval_unit' => 'month', 'billing_interval_count' => 3]);
    expect($product->licenseCostSummary()['monthly_cost_cents'])->toBeNull()->and($service->hasMissingLicenseCost())->toBeTrue();
    $product->update(['purchase_eur_amount_cents' => 12000]);
    expect($service->fresh()->monthlyCosts()->cents)->toBe(1000)->and($service->fresh()->purchasePrice()->cents)->toBe(3000);
});

it('preserves legacy costs by default', function () {
    $service = CustomerService::factory()->for(Product::factory())->yearly()->create(['purchase_price_cents' => 12000]);
    expect($service->product->hasLicenseCostModel())->toBeFalse()->and($service->monthlyCosts()->cents)->toBe(1000);
});

it('validates license settings and preserves them on partial legacy updates', function () {
    $product = licenseProduct();
    $attributes = ['name' => 'Test', 'internal_name' => 'test', 'default_billing_interval_unit' => 'month'];
    app(SaveProduct::class)($attributes, product: $product);
    expect($product->fresh()->purchase_amount_cents)->toBe(10001);
    expect(fn () => app(SaveProduct::class)(array_merge($attributes, ['purchase_units_per_pack' => 0]), product: $product))->toThrow(ValidationException::class);
    expect(fn () => app(SaveProduct::class)(array_merge($attributes, ['purchase_amount_cents' => -1]), product: $product))->toThrow(ValidationException::class);
});

it('deduplicates assigned projects while isolating each server cost pool', function () {
    $product = licenseProduct(['purchase_cost_model' => 'shared_unlimited', 'purchase_usage_unit' => 'project', 'purchase_amount_cents' => 12000]);
    $project = Project::factory()->create();
    $first = CustomerService::factory()->for($product)->create(['customer_id' => $project->customer_id, 'usage_project_id' => $project->id]);
    expect($first->yearlyCosts()->cents)->toBe(12000);
    $duplicate = CustomerService::factory()->for($product)->create(['customer_id' => $project->customer_id, 'usage_project_id' => $project->id]);
    $other = CustomerService::factory()->for($product)->create(['usage_quantity' => 1]);
    $separate = licenseProduct(['purchase_cost_model' => 'shared_unlimited', 'purchase_usage_unit' => 'project', 'purchase_amount_cents' => 60000]);
    CustomerService::factory()->for($separate)->create();
    $summary = $product->licenseCostSummary();
    expect($summary['usage_units'])->toBe(2)->and($summary['yearly_cost_cents'])->toBe(12000)
        ->and($summary['allocations'][$first->id]['yearly_cents'])->toBe(3000)
        ->and($summary['allocations'][$duplicate->id]['yearly_cents'])->toBe(3000)
        ->and($summary['allocations'][$other->id]['yearly_cents'])->toBe(6000);
});

it('rejects projects of another customer and persists valid usage in service actions', function () {
    $customer = Customer::factory()->create();
    $project = Project::factory()->create();
    $attributes = ['name' => 'Server', 'billing_interval_unit' => 'month', 'usage_quantity' => 2, 'usage_project_id' => $project->id];
    expect(fn () => app(CreateCustomerService::class)($customer, $attributes))->toThrow(ValidationException::class);
    $project->update(['customer_id' => $customer->id]);
    $service = app(CreateCustomerService::class)($customer, $attributes);
    expect($service->usage_quantity)->toBe(2)->and($service->usageProject->id)->toBe($project->id);
    app(UpdateCustomerService::class)($service, array_merge($attributes, ['usage_quantity' => 5]));
    expect($service->fresh()->usage_quantity)->toBe(5);
    expect(fn () => app(UpdateCustomerService::class)($service, array_merge($attributes, ['usage_quantity' => 0])))->toThrow(ValidationException::class);
});

it('assigns residual cents in service id order independently for each period', function () {
    $product = licenseProduct(['purchase_cost_model' => 'shared_unlimited', 'purchase_amount_cents' => 100]);
    $first = CustomerService::factory()->for($product)->create();
    $second = CustomerService::factory()->for($product)->create();
    $third = CustomerService::factory()->for($product)->create();
    $summary = $product->licenseCostSummary();
    expect($summary['allocations'][$first->id])->toBe(['monthly_cents' => 3, 'yearly_cents' => 34])
        ->and($summary['allocations'][$second->id])->toBe(['monthly_cents' => 3, 'yearly_cents' => 33])
        ->and($summary['allocations'][$third->id])->toBe(['monthly_cents' => 2, 'yearly_cents' => 33]);
});

it('preserves independently rounded annual allocations in effective annual purchase prices', function () {
    $product = licenseProduct(['purchase_cost_model' => 'shared_unlimited', 'purchase_amount_cents' => 100]);
    $service = CustomerService::factory()->for($product)->yearly()->create();
    expect($service->purchasePrice()->cents)->toBe(100);
});

it('rejects license totals beyond supported monetary range in the action', function () {
    $product = licenseProduct(['purchase_cost_model' => 'per_unit']);
    CustomerService::factory()->for($product)->create(['usage_quantity' => 2]);
    expect(fn () => app(SaveProduct::class)([
        'name' => 'Large', 'internal_name' => 'large', 'default_billing_interval_unit' => 'month',
        'purchase_amount_cents' => 1000000000000,
    ], product: $product))->toThrow(ValidationException::class);
});

it('rejects overflowing license totals before allocation arithmetic', function () {
    $product = licenseProduct(['purchase_cost_model' => 'per_unit']);
    CustomerService::factory()->for($product)->create(['usage_quantity' => 1000000000]);
    expect(fn () => app(SaveProduct::class)([
        'name' => 'Large', 'internal_name' => 'large', 'default_billing_interval_unit' => 'month',
        'purchase_amount_cents' => 1000000000000,
    ], product: $product))->toThrow(ValidationException::class);
});

it('refuses unsupported pools before returning corrupted integer allocations', function () {
    $product = licenseProduct(['purchase_cost_model' => 'per_unit', 'purchase_amount_cents' => 1000000000000]);
    CustomerService::factory()->for($product)->create(['usage_quantity' => 1000000000]);
    expect(fn () => $product->licenseCostSummary())->toThrow(ValidationException::class);
});

it('queries each license pool once while rendering many service costs', function (): void {
    $product = licenseProduct(['purchase_cost_model' => 'shared_unlimited']);
    $services = CustomerService::factory()->count(12)->for($product)->create();
    $services->each(fn ($service) => $service->setRelation('product', $product));
    DB::flushQueryLog();
    DB::enableQueryLog();
    foreach ($services as $service) {
        $service->monthlyCosts();
        $service->yearlyCosts();
        $service->hasMissingLicenseCost();
    }
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($queries)->toHaveCount(1);
});

it('refreshes cached pools after service product and project mutations', function (): void {
    $product = licenseProduct(['purchase_usage_unit' => 'project']);
    $project = Project::factory()->create();
    $first = CustomerService::factory()->for($product)->create(['usage_project_id' => $project->id, 'usage_quantity' => 3]);
    $second = CustomerService::factory()->for($product)->create(['usage_project_id' => $project->id, 'usage_quantity' => 2]);
    expect($product->licenseCostSummary()['usage_units'])->toBe(1);
    $project->delete();
    expect($product->licenseCostSummary()['usage_units'])->toBe(5);
    $second->update(['usage_quantity' => 4]);
    expect($product->licenseCostSummary()['usage_units'])->toBe(7);
    $second->delete();
    expect($product->licenseCostSummary()['usage_units'])->toBe(3);
    $product->update(['purchase_amount_cents' => 24000]);
    expect($product->licenseCostSummary()['amount_cents'])->toBe(24000);
    $candidate = clone $product;
    $candidate->purchase_amount_cents = 6000;
    expect($candidate->licenseCostSummary()['amount_cents'])->toBe(6000)
        ->and($product->licenseCostSummary()['amount_cents'])->toBe(24000);
});
