<?php

use App\Actions\Customers\ArchiveCustomer;
use App\Actions\Customers\RestoreCustomer;
use App\Actions\Services\ArchiveCustomerService;
use App\Enums\AuditEvent;
use App\Exceptions\ReadOnlyRecordException;
use App\Livewire\Shared\AuditPanel;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('protokolliert das Anlegen eines Datensatzes', function (): void {
    $customer = Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH']);

    $eintrag = $customer->auditLogs()->firstOrFail();

    expect($eintrag->event)->toBe(AuditEvent::Created)
        ->and($eintrag->new_values)->toHaveKey('company_name')
        ->and($eintrag->new_values['company_name'])->toBe('Müller Elektrotechnik GmbH');
});

it('protokolliert alte und neue Werte einer Aenderung', function (): void {
    $user = User::factory()->create(['name' => 'Katrin Berger']);
    $this->actingAs($user);

    $service = CustomerService::factory()->create(['sales_price_cents' => 4900]);
    $service->update(['sales_price_cents' => 5900]);

    $eintrag = $service->auditLogs()->where('event', AuditEvent::Updated)->firstOrFail();

    expect($eintrag->old_values['sales_price_cents'])->toBe(4900)
        ->and($eintrag->new_values['sales_price_cents'])->toBe(5900)
        ->and($eintrag->user_id)->toBe($user->id)
        ->and($eintrag->created_at)->not->toBeNull();
});

it('protokolliert Statuswechsel mit lesbaren Werten', function (): void {
    $service = CustomerService::factory()->planned()->create();

    $service->update(['status' => 'active']);

    $eintrag = $service->auditLogs()->where('event', AuditEvent::Updated)->firstOrFail();

    expect($eintrag->old_values['status'])->toBe('planned')
        ->and($eintrag->new_values['status'])->toBe('active');
});

it('unterscheidet Archivierung und Reaktivierung von normalen Aenderungen', function (): void {
    $customer = Customer::factory()->create();

    app(ArchiveCustomer::class)($customer);
    expect($customer->auditLogs()->where('event', AuditEvent::Archived)->exists())->toBeTrue();

    app(RestoreCustomer::class)($customer);
    expect($customer->auditLogs()->where('event', AuditEvent::Restored)->exists())->toBeTrue();
});

it('protokolliert die Archivierung einer Kundenleistung', function (): void {
    $service = CustomerService::factory()->create();

    app(ArchiveCustomerService::class)($service);

    expect($service->auditLogs()->where('event', AuditEvent::Archived)->exists())->toBeTrue();
});

it('nimmt Zeitstempel von der Protokollierung aus', function (): void {
    $product = Product::factory()->create();
    $product->update(['name' => 'Neuer Name']);

    $eintrag = $product->auditLogs()->where('event', AuditEvent::Updated)->firstOrFail();

    expect($eintrag->new_values)->toHaveKey('name')
        ->and($eintrag->new_values)->not->toHaveKey('updated_at');
});

it('protokolliert nichts, wenn sich fachlich nichts aendert', function (): void {
    $customer = Customer::factory()->create();

    $vorher = $customer->auditLogs()->count();

    $customer->touch();

    expect($customer->auditLogs()->count())->toBe($vorher);
});

it('macht Audit-Eintraege unveraenderlich', function (): void {
    $eintrag = Customer::factory()->create()->auditLogs()->firstOrFail();

    expect(fn () => $eintrag->update(['description' => 'Manipuliert']))
        ->toThrow(ReadOnlyRecordException::class);

    expect(fn () => $eintrag->delete())->toThrow(ReadOnlyRecordException::class);

    expect(AuditLog::query()->whereKey($eintrag->id)->exists())->toBeTrue();
});

it('haelt die IP-Adresse fest', function (): void {
    $customer = Customer::factory()->create();

    expect($customer->auditLogs()->firstOrFail()->ip_address)->not->toBeNull();
});

it('zeigt die Historie mit deutschen Feldbezeichnungen', function (): void {
    $customer = Customer::factory()->create(['short_label' => 'Alt']);
    $customer->update(['short_label' => 'Neu']);

    Livewire::actingAs(User::factory()->create())
        ->test(AuditPanel::class, ['auditable' => $customer])
        ->assertSee('Kurzbezeichnung')
        ->assertSee('Angelegt')
        ->assertSee('Geändert');
});
