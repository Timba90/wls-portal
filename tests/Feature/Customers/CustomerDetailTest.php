<?php

use App\Actions\Contacts\CreateContact;
use App\Livewire\Customers\CustomerDetail;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use Livewire\Livewire;

it('startet auf den Leistungen', function (): void {
    $kunde = Customer::factory()->create();
    CustomerService::factory()->for($kunde)->create(['name' => 'Managed Hosting']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $kunde])
        ->assertSet('tab', 'leistungen')
        ->assertSee('Managed Hosting');
});

it('wechselt zwischen den Reitern', function (): void {
    $kunde = Customer::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $kunde])
        ->set('tab', 'historie')
        ->assertSet('tab', 'historie');
});

it('fuehrt die Stammdaten eines Firmenkunden in der Reihenfolge des Entwurfs', function (): void {
    $kunde = Customer::factory()->create([
        'company_name' => 'Nordlicht Werbeagentur GmbH',
        'short_label' => 'Nordlicht',
        'internal_code' => 'NORD',
    ]);

    $stammdaten = Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $kunde])
        ->instance()
        ->masterData();

    expect(array_keys($stammdaten))->toBe([
        'Kundennummer', 'Typ', 'Firmenname', 'Kurzbezeichnung',
        'Internes Kürzel', 'Verantwortlich', 'Angelegt', 'Zuletzt geändert',
    ])->and($stammdaten['Firmenname'])->toBe('Nordlicht Werbeagentur GmbH');
});

it('nennt bei Privatkunden die persoenlichen Felder', function (): void {
    $kunde = Customer::factory()->privatePerson()->create([
        'first_name' => 'Helena',
        'last_name' => 'Roth',
    ]);

    $stammdaten = Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $kunde])
        ->instance()
        ->masterData();

    expect(array_keys($stammdaten))->toContain('Vorname', 'Nachname', 'Geburtsdatum')
        ->and($stammdaten['Nachname'])->toBe('Roth');
});

it('ergaenzt bei archivierten Kunden das Archivdatum', function (): void {
    $kunde = Customer::factory()->archived()->create();

    $stammdaten = Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $kunde])
        ->instance()
        ->masterData();

    expect(array_keys($stammdaten))->toContain('Archiviert');
});

it('zeigt die Ansprechpartner in der rechten Spalte', function (): void {
    $kunde = Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH', 'short_label' => 'Müller']);

    app(CreateContact::class)(
        attributes: ['first_name' => 'Thomas', 'last_name' => 'Lindner'],
        assignments: [['customer_id' => $kunde->id, 'is_primary_contact' => true]],
        emails: [['email' => 'thomas.lindner@muller.example.de', 'type' => 'business', 'is_primary' => true]],
    );

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerDetail::class, ['customer' => $kunde])
        ->assertSee('Thomas Lindner')
        ->assertSee('thomas.lindner@muller.example.de');
});

it('bildet die Initialen aus Vor- und Nachnamen', function (): void {
    $privat = Customer::factory()->privatePerson()->create(['first_name' => 'Helena', 'last_name' => 'Roth']);
    $firma = Customer::factory()->create(['company_name' => 'Nordlicht Werbeagentur GmbH']);

    expect($privat->initials())->toBe('HR')
        ->and($firma->initials())->toBe('NO');
});

it('stellt den Hauptansprechpartner an den Anfang', function (): void {
    $kunde = Customer::factory()->create();

    app(CreateContact::class)(
        attributes: ['first_name' => 'Beate', 'last_name' => 'Zweitrang'],
        assignments: [['customer_id' => $kunde->id, 'priority' => 1]],
    );

    app(CreateContact::class)(
        attributes: ['first_name' => 'Anton', 'last_name' => 'Hauptkontakt'],
        assignments: [['customer_id' => $kunde->id, 'is_primary_contact' => true, 'priority' => 9]],
    );

    // Trotz schlechterer Prioritaet steht der Hauptansprechpartner oben —
    // dieselbe Reihenfolge wie im Ansprechpartner-Bereich.
    expect($kunde->fresh()->contactAssignments->first()->contact->last_name)
        ->toBe('Hauptkontakt');
});
