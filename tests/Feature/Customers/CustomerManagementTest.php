<?php

use App\Actions\Customers\ArchiveCustomer;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\RestoreCustomer;
use App\Enums\ContactChannelType;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Livewire\Customers\CustomerDetail;
use App\Livewire\Customers\CustomerForm;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

it('legt einen Firmenkunden an', function (): void {
    $customer = app(CreateCustomer::class)([
        'type' => CustomerType::Company->value,
        'company_name' => 'Müller Elektrotechnik GmbH',
        'short_label' => 'Müller Elektro',
        'internal_code' => 'MUEL',
    ]);

    expect($customer->type)->toBe(CustomerType::Company)
        ->and($customer->company_name)->toBe('Müller Elektrotechnik GmbH')
        ->and($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->customer_number)->toBe('KD-00001')
        ->and($customer->displayName())->toBe('Müller Elektrotechnik GmbH')
        ->and($customer->isCompany())->toBeTrue();
});

it('legt einen Privatkunden mit Kontaktdaten an', function (): void {
    $customer = app(CreateCustomer::class)([
        'type' => CustomerType::Private->value,
        'salutation' => Salutation::Frau->value,
        'academic_title' => 'Dr.',
        'first_name' => 'Beate',
        'last_name' => 'Stolzenberg',
        'gender' => Gender::Female->value,
        'birth_date' => '1979-04-12',
        'short_label' => 'Beate Stolzenberg',
        'internal_code' => 'STOL',
        'emails' => [
            ['email' => 'b.stolzenberg@example.de', 'type' => ContactChannelType::Private->value, 'is_primary' => true],
            ['email' => 'stolzenberg@arbeit.example.de', 'type' => ContactChannelType::Business->value],
        ],
        'phones' => [
            ['number' => '+49 30 1234567', 'type' => ContactChannelType::Private->value],
            ['number' => '+49 170 7654321', 'type' => ContactChannelType::Mobile->value, 'is_primary' => true],
        ],
    ]);

    expect($customer->isPrivate())->toBeTrue()
        ->and($customer->company_name)->toBeNull()
        ->and($customer->displayName())->toBe('Dr. Beate Stolzenberg')
        ->and($customer->birth_date->format('Y-m-d'))->toBe('1979-04-12')
        ->and($customer->emailAddresses)->toHaveCount(2)
        ->and($customer->phoneNumbers)->toHaveCount(2)
        ->and($customer->primaryEmailAddress()->email)->toBe('b.stolzenberg@example.de')
        ->and($customer->primaryPhoneNumber()->number)->toBe('+49 170 7654321');
});

it('leert typfremde Felder beim Anlegen', function (): void {
    $customer = app(CreateCustomer::class)([
        'type' => CustomerType::Company->value,
        'company_name' => 'Hansen Logistik GmbH',
        // Privatkundenfelder werden mitgeschickt, duerfen aber nicht landen.
        'first_name' => 'Sollte',
        'last_name' => 'Verschwinden',
        'gender' => Gender::Male->value,
        'short_label' => 'Hansen',
        'internal_code' => 'HANS',
    ]);

    expect($customer->first_name)->toBeNull()
        ->and($customer->last_name)->toBeNull()
        ->and($customer->gender)->toBeNull();
});

it('legt fuer Firmenkunden keine eigenen Kontaktkanaele an', function (): void {
    $customer = app(CreateCustomer::class)([
        'type' => CustomerType::Company->value,
        'company_name' => 'Nordlicht Werbeagentur GmbH',
        'short_label' => 'Nordlicht',
        'internal_code' => 'NORD',
        'emails' => [['email' => 'info@nordlicht.example.de', 'type' => ContactChannelType::Business->value]],
    ]);

    expect($customer->emailAddresses)->toHaveCount(0);
});

it('markiert genau eine E-Mail-Adresse und Telefonnummer als primaer', function (): void {
    $customer = app(CreateCustomer::class)([
        'type' => CustomerType::Private->value,
        'first_name' => 'Andreas',
        'last_name' => 'Kowalski',
        'short_label' => 'Andreas Kowalski',
        'internal_code' => 'KOWA',
        'emails' => [
            ['email' => 'eins@example.de', 'type' => ContactChannelType::Private->value, 'is_primary' => true],
            ['email' => 'zwei@example.de', 'type' => ContactChannelType::Private->value, 'is_primary' => true],
            ['email' => 'drei@example.de', 'type' => ContactChannelType::Private->value, 'is_primary' => true],
        ],
        'phones' => [
            ['number' => '+49 30 1111111', 'type' => ContactChannelType::Private->value],
            ['number' => '+49 30 2222222', 'type' => ContactChannelType::Private->value],
        ],
    ]);

    expect($customer->emailAddresses->where('is_primary', true))->toHaveCount(1)
        ->and($customer->primaryEmailAddress()->email)->toBe('eins@example.de')
        ->and($customer->phoneNumbers->where('is_primary', true))->toHaveCount(1)
        // Ohne ausdrueckliche Markierung wird der erste Eintrag primaer.
        ->and($customer->primaryPhoneNumber()->number)->toBe('+49 30 1111111');
});

it('archiviert einen Kunden und hebt die Archivierung wieder auf', function (): void {
    $customer = Customer::factory()->create();

    app(ArchiveCustomer::class)($customer);

    expect($customer->status)->toBe(CustomerStatus::Archived)
        ->and($customer->archived_at)->not->toBeNull()
        ->and($customer->isArchived())->toBeTrue();

    app(RestoreCustomer::class)($customer);

    expect($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->archived_at)->toBeNull();
});

it('legt einen Firmenkunden ueber das Formular an', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerForm::class)
        ->set('type', CustomerType::Company->value)
        ->set('company_name', 'Bergmann & Sohn Bauunternehmung KG')
        ->set('short_label', 'Bergmann Bau')
        ->set('internal_code', 'BERG')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Customer::query()->where('company_name', 'Bergmann & Sohn Bauunternehmung KG')->exists())->toBeTrue();
});

it('verlangt bei Firmenkunden einen Firmennamen', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerForm::class)
        ->set('type', CustomerType::Company->value)
        ->set('short_label', 'Ohne Name')
        ->set('internal_code', 'OHNE')
        ->call('save')
        ->assertHasErrors('company_name');
});

it('verlangt bei Privatkunden Vor- und Nachnamen', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerForm::class)
        ->set('type', CustomerType::Private->value)
        ->set('short_label', 'Ohne Name')
        ->set('internal_code', 'OHNE')
        ->call('save')
        ->assertHasErrors(['first_name', 'last_name']);
});

it('laesst den Kundentyp beim Bearbeiten unveraendert', function (): void {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerForm::class, ['customer' => $customer])
        ->set('company_name', 'Neuer Firmenname GmbH')
        ->call('save')
        ->assertHasNoErrors();

    expect($customer->fresh())
        ->company_name->toBe('Neuer Firmenname GmbH')
        ->type->toBe(CustomerType::Company);
});

it('archiviert einen Kunden ueber die Detailseite', function (): void {
    $customer = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $customer])
        ->call('archive');

    expect($customer->fresh()->isArchived())->toBeTrue();
});
