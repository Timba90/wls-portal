<?php

use App\Models\Certificate;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Models\User;

it('shows inventory billing badges and opens the linked customer service', function (string $path, string $model): void {
    $this->actingAs(User::factory()->create());
    $service = CustomerService::factory()->create(['name' => 'Domainpaket Filterprobe', 'status' => 'active', 'do_not_bill' => false, 'billing_interval_unit' => 'month']);
    $excluded = CustomerService::factory()->create(['name' => 'Kostenlose Domainprobe', 'status' => 'active', 'do_not_bill' => true]);
    $model::factory()->create(['customer_id' => $service->customer_id, 'customer_service_id' => $service->id]);
    $model::factory()->create(['customer_id' => $excluded->customer_id, 'customer_service_id' => $excluded->id]);
    $model::factory()->create();

    visit($path)
        ->assertSee('Abrechnung')
        ->assertSee('Abrechenbar')
        ->assertSee('Nicht abrechnen')
        ->assertSee('Keine Leistung zugeordnet')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: $path === '/domains' ? 'domain-billing-status' : 'certificate-billing-status')
        ->click('Domainpaket Filterprobe')
        ->assertPathIs('/kunden/'.$service->customer_id.'/leistungen/'.$service->id)
        ->assertNoJavaScriptErrors();
})->with([
    'domains' => ['/domains', Domain::class],
    'certificates' => ['/zertifikate', Certificate::class],
]);
