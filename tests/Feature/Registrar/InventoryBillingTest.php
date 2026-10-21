<?php

use App\Livewire\Registrar\CertificateList;
use App\Livewire\Registrar\DomainDetail;
use App\Livewire\Registrar\DomainList;
use App\Models\Certificate;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Models\User;
use Livewire\Livewire;

it('shows the linked billing setting in the default inventory columns', function (string $component, string $model, ?array $attributes, string $label): void {
    $service = $attributes === null ? null : CustomerService::factory()->create($attributes);
    $model::factory()->create(['customer_id' => $service?->customer_id, 'customer_service_id' => $service?->id]);

    $test = Livewire::actingAs(User::factory()->create())->test($component)
        ->assertSee('Abrechnung')
        ->assertSee($label);

    if ($service) {
        $test->assertSee(route('customer-services.show', [$service->customer_id, $service]), escape: false);
    }
})->with([
    'domains' => [DomainList::class, Domain::class],
    'certificates' => [CertificateList::class, Certificate::class],
])->with([
    'recurring' => [['status' => 'active', 'do_not_bill' => false, 'billing_interval_unit' => 'month'], 'Abrechenbar'],
    'excluded' => [['status' => 'active', 'do_not_bill' => true], 'Nicht abrechnen'],
    'once' => [['status' => 'active', 'do_not_bill' => false, 'billing_interval_unit' => 'once'], 'Einmalig'],
    'planned' => [['status' => 'planned'], 'Geplant'],
    'paused' => [['status' => 'paused'], 'Pausiert'],
    'ended' => [['status' => 'ended'], 'Beendet'],
    'archived' => [['status' => 'archived'], 'Archiviert'],
    'unlinked' => [null, 'Keine Leistung zugeordnet'],
]);

it('shows domain billing details without claiming that an unlinked domain is unpaid', function (): void {
    $domain = Domain::factory()->create();
    Livewire::actingAs(User::factory()->create())->test(DomainDetail::class, ['domain' => $domain])
        ->assertSee('Abrechnung')
        ->assertSee('Keine Leistung zugeordnet')
        ->assertDontSee('nicht einzeln abgerechnet');
});
