<?php

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Livewire\Registrar\DomainDetail;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomFieldDefinition;
use App\Models\Domain;
use App\Models\Note;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('verlangt eine Anmeldung', function (): void {
    $domain = Domain::factory()->create();

    $this->get(route('domains.show', $domain))->assertRedirect(route('login'));
});

it('zeigt den technischen Stand aus der Schnittstelle', function (): void {
    $domain = Domain::factory()->create([
        'name' => 'beispiel.de',
        'status' => 'active',
        'auto_renew' => true,
        'expires_on' => now()->addYear(),
        'nameservers' => ['ns1.beispiel.de', 'ns2.beispiel.de'],
    ]);

    $this->actingAs($this->benutzer)
        ->get(route('domains.show', $domain))
        ->assertOk()
        ->assertSee('beispiel.de')
        ->assertSee('Automatisch')
        ->assertSee('ns1.beispiel.de')
        ->assertSee('ns2.beispiel.de');
});

it('zeigt Kunde und Leistung der Zuordnung', function (): void {
    $kunde = Customer::factory()->create(['company_name' => 'Müller Elektro GmbH']);
    $leistung = CustomerService::factory()->for($kunde)->create(['name' => 'Domainpaket']);
    $domain = Domain::factory()->create([
        'customer_id' => $kunde->id,
        'customer_service_id' => $leistung->id,
    ]);

    $this->actingAs($this->benutzer)
        ->get(route('domains.show', $domain))
        ->assertOk()
        ->assertSee('Müller Elektro GmbH')
        ->assertSee('Domainpaket');
});

it('weist eine Domain ohne Kunde als solche aus', function (): void {
    $domain = Domain::factory()->create();

    $this->actingAs($this->benutzer)
        ->get(route('domains.show', $domain))
        ->assertOk()
        ->assertSee('Ohne Kunde');
});

it('ordnet die Domain einem Kunden zu und zeigt den neuen Stand', function (): void {
    $kunde = Customer::factory()->create(['company_name' => 'Müller Elektro GmbH']);
    $domain = Domain::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(DomainDetail::class, ['domain' => $domain])
        ->call('editAssignment')
        ->assertSet('showAssignmentForm', true)
        ->set('assignmentCustomerId', (string) $kunde->id)
        ->call('saveAssignment')
        ->assertSet('showAssignmentForm', false)
        ->assertDispatched('zuordnung-gespeichert')
        // Der Kundenname allein taugt nicht: er steht auch in der Auswahlliste
        // des Dialogs. Den Link gibt es nur im Zuordnungsblock — und nur, wenn
        // das Bauteil den neuen Stand geladen hat.
        ->assertSeeHtml(route('customers.show', $kunde));

    expect($domain->fresh()->customer_id)->toBe($kunde->id);
});

it('lehnt eine Leistung ab, die einem anderen Kunden gehört', function (): void {
    $kunde = Customer::factory()->create();
    $fremderKunde = Customer::factory()->create();
    $fremdeLeistung = CustomerService::factory()->for($fremderKunde)->create();
    $domain = Domain::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(DomainDetail::class, ['domain' => $domain])
        ->call('editAssignment')
        ->set('assignmentCustomerId', (string) $kunde->id)
        ->set('assignmentServiceId', (string) $fremdeLeistung->id)
        ->call('saveAssignment')
        ->assertHasErrors('assignmentServiceId');

    expect($domain->fresh()->customer_service_id)->toBeNull();
});

it('führt Notizen, Dokumente, eigene Felder und Verlauf als Bereiche', function (): void {
    $domain = Domain::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(DomainDetail::class, ['domain' => $domain])
        ->assertSet('tab', 'notizen')
        ->assertSee('Notizen')
        ->assertSee('Dokumente')
        ->assertSee('Eigene Felder')
        ->set('tab', 'verlauf')
        ->assertSee('Änderungen an dieser Domain');
});

it('zeigt eine Notiz zur Domain', function (): void {
    $domain = Domain::factory()->create();

    Note::factory()->create([
        'notable_type' => Domain::class,
        'notable_id' => $domain->id,
        'body' => 'Umzug auf neuen Nameserver geplant',
        'user_id' => $this->benutzer->id,
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(DomainDetail::class, ['domain' => $domain])
        ->assertSee('Umzug auf neuen Nameserver geplant');
});

it('zeigt eigene Felder des Bereichs Domain', function (): void {
    CustomFieldDefinition::factory()
        ->forEntity(CustomFieldEntity::Domain)
        ->ofType(CustomFieldType::Date)
        ->create(['name' => 'Gekündigt zum', 'key' => 'gekuendigt_zum']);

    $domain = Domain::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(DomainDetail::class, ['domain' => $domain])
        ->set('tab', 'felder')
        ->assertSee('Gekündigt zum');
});
