<?php

use App\Actions\Registrar\AssignInventory;
use App\Livewire\Registrar\CertificateList;
use App\Livewire\Registrar\DomainList;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('ordnet eine Domain einem Kunden zu', function (): void {
    $domain = Domain::factory()->create();
    $kunde = Customer::factory()->create();
    $leistung = CustomerService::factory()->for($kunde)->create();

    Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->call('startAssignment', $domain->id)
        ->assertSet('showAssignmentForm', true)
        ->set('assignmentCustomerId', (string) $kunde->id)
        ->set('assignmentServiceId', (string) $leistung->id)
        ->call('saveAssignment')
        ->assertHasNoErrors()
        ->assertDispatched('zuordnung-gespeichert')
        ->assertSet('showAssignmentForm', false);

    expect($domain->refresh()->customer_id)->toBe($kunde->id)
        ->and($domain->customer_service_id)->toBe($leistung->id);
});

it('zeigt beim Öffnen die bestehende Zuordnung', function (): void {
    $kunde = Customer::factory()->create();
    $leistung = CustomerService::factory()->for($kunde)->create();
    $domain = Domain::factory()->create([
        'customer_id' => $kunde->id,
        'customer_service_id' => $leistung->id,
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->call('startAssignment', $domain->id)
        ->assertSet('assignmentCustomerId', (string) $kunde->id)
        ->assertSet('assignmentServiceId', (string) $leistung->id);
});

it('nimmt die Zuordnung wieder zurueck', function (): void {
    $kunde = Customer::factory()->create();
    $leistung = CustomerService::factory()->for($kunde)->create();
    $domain = Domain::factory()->create([
        'customer_id' => $kunde->id,
        'customer_service_id' => $leistung->id,
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->call('startAssignment', $domain->id)
        ->set('assignmentCustomerId', '')
        ->call('saveAssignment')
        ->assertHasNoErrors();

    // Ohne Kunde darf auch keine Leistung stehen bleiben.
    expect($domain->refresh()->customer_id)->toBeNull()
        ->and($domain->customer_service_id)->toBeNull();
});

it('vergisst die Leistung, wenn der Kunde gewechselt wird', function (): void {
    $alt = Customer::factory()->create();
    $leistung = CustomerService::factory()->for($alt)->create();
    $neu = Customer::factory()->create();
    $domain = Domain::factory()->create([
        'customer_id' => $alt->id,
        'customer_service_id' => $leistung->id,
    ]);

    // Sonst hinge die Leistung des alten Kunden am neuen.
    Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->call('startAssignment', $domain->id)
        ->set('assignmentCustomerId', (string) $neu->id)
        ->assertSet('assignmentServiceId', null);
});

it('weist eine Leistung eines anderen Kunden ab', function (): void {
    $kunde = Customer::factory()->create();
    $fremd = Customer::factory()->create();
    $fremdeLeistung = CustomerService::factory()->for($fremd)->create();
    $domain = Domain::factory()->create();

    expect(fn () => app(AssignInventory::class)($domain, $kunde, $fremdeLeistung))
        ->toThrow(InvalidArgumentException::class, 'gehört einem anderen Kunden');

    expect($domain->refresh()->customer_id)->toBeNull();
});

it('weist eine Leistung ohne Kunden ab', function (): void {
    $leistung = CustomerService::factory()->create();
    $domain = Domain::factory()->create();

    expect(fn () => app(AssignInventory::class)($domain, null, $leistung))
        ->toThrow(InvalidArgumentException::class, 'ohne Kunde');
});

it('bietet nur Leistungen des gewaehlten Kunden an', function (): void {
    $kunde = Customer::factory()->create();
    $eigene = CustomerService::factory()->for($kunde)->create(['name' => 'Eigene Leistung']);
    CustomerService::factory()->for($kunde)->create(['name' => 'Archivierte', 'archived_at' => now()]);
    CustomerService::factory()->create(['name' => 'Fremde Leistung']);

    $komponente = Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->set('assignmentCustomerId', (string) $kunde->id);

    $namen = $komponente->instance()->assignableServices()->pluck('name')->all();

    expect($namen)->toContain($eigene->billing_label ?: 'Eigene Leistung')
        ->and($namen)->not->toContain('Fremde Leistung')
        ->and($namen)->not->toContain('Archivierte');
});

it('ordnet auch ein Zertifikat zu', function (): void {
    $zertifikat = Certificate::factory()->create();
    $kunde = Customer::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(CertificateList::class)
        ->call('startAssignment', $zertifikat->id)
        ->set('assignmentCustomerId', (string) $kunde->id)
        ->call('saveAssignment')
        ->assertDispatched('zuordnung-gespeichert');

    expect($zertifikat->refresh()->customer_id)->toBe($kunde->id);
});

it('haelt die Zuordnung in der Aenderungshistorie fest', function (): void {
    $domain = Domain::factory()->create();
    $kunde = Customer::factory()->create();

    app(AssignInventory::class)($domain, $kunde);

    expect($domain->auditLogs()->where('event', 'updated')->exists())->toBeTrue();
});

it('zeigt die zugeordnete Leistung in der Liste', function (): void {
    $kunde = Customer::factory()->create();
    $leistung = CustomerService::factory()->for($kunde)->create([
        'name' => 'Domainpaket',
        'billing_label' => 'Domainpaket klein',
    ]);

    Domain::factory()->create([
        'name' => 'beispiel.de',
        'customer_id' => $kunde->id,
        'customer_service_id' => $leistung->id,
    ]);

    // Die Spalte ist zuschaltbar; sichtbar gemacht muss sie den Namen zeigen.
    Livewire::actingAs($this->benutzer)
        ->test(DomainList::class)
        ->call('toggleColumn', 'service')
        ->assertSee('Domainpaket klein');
});
