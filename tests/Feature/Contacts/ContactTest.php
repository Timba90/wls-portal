<?php

use App\Actions\Contacts\ArchiveContact;
use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\UpdateContact;
use App\Enums\ContactChannelType;
use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

it('legt einen Ansprechpartner mit Kundenzuordnung an', function (): void {
    $customer = Customer::factory()->create();
    $rolle = ContactRole::factory()->create(['name' => 'Geschäftsführung']);

    $contact = app(CreateContact::class)(
        attributes: [
            'salutation' => Salutation::Herr->value,
            'first_name' => 'Thomas',
            'last_name' => 'Lindner',
            'gender' => Gender::Male->value,
            'preferred_contact_method' => ContactMethod::Email->value,
        ],
        assignments: [[
            'customer_id' => $customer->id,
            'role_ids' => [$rolle->id],
            'is_primary_contact' => true,
        ]],
    );

    expect($contact->fullName())->toBe('Thomas Lindner')
        ->and($contact->listName())->toBe('Lindner, Thomas')
        ->and($contact->assignments)->toHaveCount(1)
        ->and($contact->assignments->first()->roles->pluck('name')->all())->toBe(['Geschäftsführung'])
        ->and($contact->assignments->first()->is_primary_contact)->toBeTrue();
});

it('verlangt mindestens eine Kundenzuordnung', function (): void {
    expect(fn () => app(CreateContact::class)(
        attributes: ['first_name' => 'Ohne', 'last_name' => 'Kunde'],
        assignments: [],
    ))->toThrow(ValidationException::class);

    expect(Contact::query()->count())->toBe(0);
});

it('speichert mehrere E-Mail-Adressen mit genau einer primaeren', function (): void {
    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Katrin', 'last_name' => 'Vogt'],
        assignments: [['customer_id' => Customer::factory()->create()->id]],
        emails: [
            ['email' => 'k.vogt@example.de', 'type' => ContactChannelType::Business->value],
            ['email' => 'katrin@privat.example.de', 'type' => ContactChannelType::Private->value, 'is_primary' => true],
        ],
    );

    expect($contact->emailAddresses)->toHaveCount(2)
        ->and($contact->emailAddresses->where('is_primary', true))->toHaveCount(1)
        ->and($contact->primaryEmailAddress()->email)->toBe('katrin@privat.example.de');
});

it('speichert mehrere Telefonnummern mit genau einer primaeren', function (): void {
    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Michael', 'last_name' => 'Sauerbier'],
        assignments: [['customer_id' => Customer::factory()->create()->id]],
        phones: [
            ['number' => '+49 211 1234567', 'type' => ContactChannelType::Business->value, 'is_primary' => true],
            ['number' => '+49 151 7654321', 'type' => ContactChannelType::Mobile->value],
            ['number' => '+49 211 9999999', 'type' => ContactChannelType::Private->value],
        ],
    );

    expect($contact->phoneNumbers)->toHaveCount(3)
        ->and($contact->phoneNumbers->where('is_primary', true))->toHaveCount(1)
        ->and($contact->primaryPhoneNumber()->number)->toBe('+49 211 1234567');
});

it('ordnet einen Ansprechpartner mehreren Kunden mit unterschiedlichen Rollen zu', function (): void {
    $ersterKunde = Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH']);
    $zweiterKunde = Customer::factory()->create(['company_name' => 'Hansen Logistik GmbH']);

    $technik = ContactRole::factory()->create(['name' => 'Technik']);
    $buchhaltung = ContactRole::factory()->create(['name' => 'Buchhaltung']);

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Petra', 'last_name' => 'Neumann'],
        assignments: [
            ['customer_id' => $ersterKunde->id, 'role_ids' => [$technik->id], 'is_primary_contact' => true],
            ['customer_id' => $zweiterKunde->id, 'role_ids' => [$buchhaltung->id], 'is_billing_contact' => true],
        ],
    );

    expect($contact->customers)->toHaveCount(2);

    $ersteZuordnung = $contact->assignments->firstWhere('customer_id', $ersterKunde->id);
    $zweiteZuordnung = $contact->assignments->firstWhere('customer_id', $zweiterKunde->id);

    expect($ersteZuordnung->roles->pluck('name')->all())->toBe(['Technik'])
        ->and($ersteZuordnung->is_primary_contact)->toBeTrue()
        ->and($zweiteZuordnung->roles->pluck('name')->all())->toBe(['Buchhaltung'])
        ->and($zweiteZuordnung->is_primary_contact)->toBeFalse()
        ->and($zweiteZuordnung->is_billing_contact)->toBeTrue();
});

it('erlaubt mehrere Rollen je Kundenzuordnung', function (): void {
    $customer = Customer::factory()->create();
    $rollen = ContactRole::factory()->count(3)->create();

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Stefan', 'last_name' => 'Kohl'],
        assignments: [['customer_id' => $customer->id, 'role_ids' => $rollen->pluck('id')->all()]],
    );

    expect($contact->assignments->first()->roles)->toHaveCount(3);
});

it('erlaubt mehrere Hauptansprechpartner bei einem Kunden', function (): void {
    $customer = Customer::factory()->create();

    foreach (['Anja Weiß', 'Dirk Ostermann'] as $name) {
        [$vorname, $nachname] = explode(' ', $name);

        app(CreateContact::class)(
            attributes: ['first_name' => $vorname, 'last_name' => $nachname],
            assignments: [['customer_id' => $customer->id, 'is_primary_contact' => true]],
        );
    }

    expect($customer->contactAssignments()->where('is_primary_contact', true)->count())->toBe(2);
});

it('nutzt je Kundenzuordnung eine abweichende primaere Adresse', function (): void {
    $ersterKunde = Customer::factory()->create();
    $zweiterKunde = Customer::factory()->create();

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Silke', 'last_name' => 'Baumgart'],
        assignments: [
            ['customer_id' => $ersterKunde->id],
            ['customer_id' => $zweiterKunde->id],
        ],
        emails: [
            ['email' => 'standard@example.de', 'type' => ContactChannelType::Business->value, 'is_primary' => true],
            ['email' => 'abweichend@example.de', 'type' => ContactChannelType::Business->value],
        ],
    );

    $abweichend = $contact->emailAddresses->firstWhere('email', 'abweichend@example.de');

    $zweiteZuordnung = $contact->assignments->firstWhere('customer_id', $zweiterKunde->id);
    $zweiteZuordnung->update(['primary_email_id' => $abweichend->id]);

    $contact->refresh();

    expect($contact->assignments->firstWhere('customer_id', $ersterKunde->id)->effectiveEmail()->email)
        ->toBe('standard@example.de')
        ->and($contact->assignments->firstWhere('customer_id', $zweiterKunde->id)->effectiveEmail()->email)
        ->toBe('abweichend@example.de');
});

it('faellt bei der bevorzugten Kontaktart auf den Ansprechpartner zurueck', function (): void {
    $customer = Customer::factory()->create();

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Ralf', 'last_name' => 'Hennig', 'preferred_contact_method' => ContactMethod::Email->value],
        assignments: [['customer_id' => $customer->id, 'preferred_contact_method' => ContactMethod::Mobile->value]],
    );

    $zuordnung = $contact->assignments->first();

    expect($zuordnung->effectiveContactMethod())->toBe(ContactMethod::Mobile);

    $zuordnung->update(['preferred_contact_method' => null]);
    $contact->refresh();

    expect($contact->assignments->first()->effectiveContactMethod())->toBe(ContactMethod::Email);
});

it('entfernt beim Aktualisieren nicht mehr genannte Zuordnungen', function (): void {
    $ersterKunde = Customer::factory()->create();
    $zweiterKunde = Customer::factory()->create();

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Miriam', 'last_name' => 'Kluge'],
        assignments: [
            ['customer_id' => $ersterKunde->id],
            ['customer_id' => $zweiterKunde->id],
        ],
    );

    expect($contact->assignments)->toHaveCount(2);

    app(UpdateContact::class)(
        $contact,
        ['first_name' => 'Miriam', 'last_name' => 'Kluge'],
        [['customer_id' => $ersterKunde->id]],
    );

    expect($contact->fresh()->assignments)->toHaveCount(1);
});

it('archiviert einen Ansprechpartner ohne die Zuordnungen zu verlieren', function (): void {
    $customer = Customer::factory()->create();

    $contact = app(CreateContact::class)(
        attributes: ['first_name' => 'Bernd', 'last_name' => 'Schuster'],
        assignments: [['customer_id' => $customer->id]],
    );

    app(ArchiveContact::class)($contact);

    expect($contact->isArchived())->toBeTrue()
        ->and($contact->fresh()->assignments)->toHaveCount(1)
        ->and(Contact::query()->active()->count())->toBe(0);
});
