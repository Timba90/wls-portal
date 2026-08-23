<?php

use App\Actions\Contacts\CreateContact;
use App\Livewire\Contacts\ContactForm;
use App\Livewire\Contacts\ContactList;
use App\Livewire\Contacts\ContactRoleList;
use App\Livewire\Contacts\CustomerContacts;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\ContactRole;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

it('legt einen Ansprechpartner ueber das Formular an', function (): void {
    $customer = Customer::factory()->create();
    $rolle = ContactRole::factory()->create(['name' => 'Technik']);

    Livewire::actingAs(User::factory()->create())
        ->test(ContactForm::class)
        ->set('first_name', 'Oliver')
        ->set('last_name', 'Frenzel')
        ->set('assignments.0.customer_id', (string) $customer->id)
        ->set('assignments.0.role_ids', [$rolle->id])
        ->set('emails.0.email', 'o.frenzel@example.de')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $contact = Contact::query()->where('last_name', 'Frenzel')->firstOrFail();

    expect($contact->assignments)->toHaveCount(1)
        ->and($contact->primaryEmailAddress()->email)->toBe('o.frenzel@example.de');
});

it('lehnt ein Formular ohne Kunden ab', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ContactForm::class)
        ->set('first_name', 'Ohne')
        ->set('last_name', 'Kunde')
        ->call('save')
        ->assertHasErrors('assignments.0.customer_id');

    expect(Contact::query()->count())->toBe(0);
});

it('uebernimmt den Kunden aus der Kundendetailseite', function (): void {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(ContactForm::class, ['customerId' => $customer->id])
        ->assertSet('assignments.0.customer_id', (string) $customer->id);
});

it('bietet nur aktive Firmenkunden zur Zuordnung an', function (): void {
    Customer::factory()->create(['company_name' => 'Aktive Firma GmbH']);
    Customer::factory()->archived()->create(['company_name' => 'Archivierte Firma GmbH']);
    Customer::factory()->privatePerson()->create(['first_name' => 'Privater', 'last_name' => 'Kunde']);

    $angeboteneKunden = Livewire::actingAs(User::factory()->create())
        ->test(ContactForm::class)
        ->viewData('customers')
        ->pluck('label');

    expect($angeboteneKunden)->toHaveCount(1)
        ->and($angeboteneKunden->first())->toContain('Aktive Firma GmbH');
});

it('durchsucht Ansprechpartner ueber Name, E-Mail und Kunde', function (string $suchbegriff): void {
    $customer = Customer::factory()->create(['company_name' => 'Nordlicht Werbeagentur GmbH', 'short_label' => 'Nordlicht']);

    app(CreateContact::class)(
        attributes: ['first_name' => 'Nadine', 'last_name' => 'Pohlmann'],
        assignments: [['customer_id' => $customer->id]],
        emails: [['email' => 'n.pohlmann@nordlicht.example.de', 'type' => 'business']],
    );

    app(CreateContact::class)(
        attributes: ['first_name' => 'Sven', 'last_name' => 'Dittmar'],
        assignments: [['customer_id' => Customer::factory()->create(['company_name' => 'Andere GmbH', 'short_label' => 'Andere'])->id]],
    );

    Livewire::actingAs(User::factory()->create())
        ->test(ContactList::class)
        ->set('search', $suchbegriff)
        ->assertSee('Pohlmann')
        ->assertDontSee('Dittmar');
})->with([
    'Nachname' => 'Pohlmann',
    'E-Mail' => 'n.pohlmann@',
    'Kundenname' => 'Nordlicht',
]);

it('filtert Ansprechpartner nach Rolle', function (): void {
    $customer = Customer::factory()->create();
    $technik = ContactRole::factory()->create(['name' => 'Technik']);
    $buchhaltung = ContactRole::factory()->create(['name' => 'Buchhaltung']);

    app(CreateContact::class)(
        attributes: ['first_name' => 'Ulrike', 'last_name' => 'Brenner'],
        assignments: [['customer_id' => $customer->id, 'role_ids' => [$technik->id]]],
    );

    app(CreateContact::class)(
        attributes: ['first_name' => 'Marco', 'last_name' => 'Wendland'],
        assignments: [['customer_id' => $customer->id, 'role_ids' => [$buchhaltung->id]]],
    );

    Livewire::actingAs(User::factory()->create())
        ->test(ContactList::class)
        ->set('roleId', (string) $technik->id)
        ->assertSee('Brenner')
        ->assertDontSee('Wendland');
});

it('blendet archivierte Ansprechpartner standardmaessig aus', function (): void {
    $customer = Customer::factory()->create();

    app(CreateContact::class)(
        attributes: ['first_name' => 'Elke', 'last_name' => 'Sandner'],
        assignments: [['customer_id' => $customer->id]],
    );

    $archiviert = app(CreateContact::class)(
        attributes: ['first_name' => 'Frank', 'last_name' => 'Riedel'],
        assignments: [['customer_id' => $customer->id]],
    );
    $archiviert->forceFill(['archived_at' => now()])->save();

    Livewire::actingAs(User::factory()->create())
        ->test(ContactList::class)
        ->assertSee('Sandner')
        ->assertDontSee('Riedel');
});

it('entfernt eine Zuordnung ueber die Kundendetailseite', function (): void {
    $customer = Customer::factory()->create();

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Tanja', 'last_name' => 'Kirchner'],
        assignments: [['customer_id' => $customer->id]],
    );

    $assignmentId = $contact->assignments->first()->id;

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerContacts::class, ['customer' => $customer])
        ->call('detachAssignment', $assignmentId);

    expect(ContactAssignment::query()->whereKey($assignmentId)->exists())->toBeFalse()
        ->and(Contact::query()->whereKey($contact->id)->exists())->toBeTrue();
});

it('entfernt keine Zuordnung eines anderen Kunden', function (): void {
    $ersterKunde = Customer::factory()->create();
    $zweiterKunde = Customer::factory()->create();

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Hendrik', 'last_name' => 'Stauber'],
        assignments: [['customer_id' => $zweiterKunde->id]],
    );

    $assignmentId = $contact->assignments->first()->id;

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerContacts::class, ['customer' => $ersterKunde])
        ->call('detachAssignment', $assignmentId);

    expect(ContactAssignment::query()->whereKey($assignmentId)->exists())->toBeTrue();
});

it('speichert Vertretungen je Rolle mit Prioritaet', function (): void {
    $customer = Customer::factory()->create();
    $rolle = ContactRole::factory()->create(['name' => 'Technik']);

    $ersterKontakt = app(CreateContact::class)(
        attributes: ['first_name' => 'Britta', 'last_name' => 'Mahler'],
        assignments: [['customer_id' => $customer->id, 'role_ids' => [$rolle->id]]],
    );

    $zweiterKontakt = app(CreateContact::class)(
        attributes: ['first_name' => 'Kai', 'last_name' => 'Wernicke'],
        assignments: [['customer_id' => $customer->id]],
    );

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerContacts::class, ['customer' => $customer])
        ->set('deputies', [
            ['contact_role_id' => (string) $rolle->id, 'contact_id' => (string) $ersterKontakt->id, 'priority' => 10],
            ['contact_role_id' => (string) $rolle->id, 'contact_id' => (string) $zweiterKontakt->id, 'priority' => 20],
        ])
        ->call('saveDeputies')
        ->assertHasNoErrors();

    $vertretungen = $customer->contactDeputies()->orderBy('priority')->get();

    expect($vertretungen)->toHaveCount(2)
        ->and($vertretungen->first()->contact_id)->toBe($ersterKontakt->id)
        ->and($vertretungen->first()->priority)->toBe(10);
});

it('legt eine Ansprechpartnerrolle an und bearbeitet sie', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ContactRoleList::class)
        ->call('create')
        ->set('name', 'Qualitätsmanagement')
        ->set('description', 'Zertifizierungen und Audits')
        ->call('save')
        ->assertHasNoErrors();

    $rolle = ContactRole::query()->where('name', 'Qualitätsmanagement')->firstOrFail();

    Livewire::actingAs(User::factory()->create())
        ->test(ContactRoleList::class)
        ->call('edit', $rolle->id)
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($rolle->fresh()->is_active)->toBeFalse();
});

it('lehnt eine bereits vergebene Rollenbezeichnung ab', function (): void {
    ContactRole::factory()->create(['name' => 'Technik']);

    Livewire::actingAs(User::factory()->create())
        ->test(ContactRoleList::class)
        ->call('create')
        ->set('name', 'Technik')
        ->call('save')
        ->assertHasErrors('name');
});
