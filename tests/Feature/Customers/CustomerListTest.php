<?php

use App\Actions\Contacts\CreateContact;
use App\Enums\CustomerType;
use App\Livewire\Customers\CustomerList;
use App\Models\Customer;
use App\Models\TableConfiguration;
use App\Models\User;
use Livewire\Livewire;

it('zeigt standardmaessig nur aktive Kunden', function (): void {
    Customer::factory()->create(['company_name' => 'Aktive Firma GmbH', 'short_label' => 'Aktiv']);
    Customer::factory()->archived()->create(['company_name' => 'Archivierte Firma GmbH', 'short_label' => 'Archiv']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->assertSee('Aktive Firma GmbH')
        ->assertDontSee('Archivierte Firma GmbH');
});

it('zeigt archivierte Kunden auf Wunsch', function (): void {
    Customer::factory()->archived()->create(['company_name' => 'Archivierte Firma GmbH', 'short_label' => 'Archiv']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->set('status', 'archived')
        ->assertSee('Archivierte Firma GmbH');
});

it('durchsucht Nummer, Namen, Kurzbezeichnung und Kuerzel', function (string $suchbegriff): void {
    Customer::factory()->create([
        'company_name' => 'Steinbach Steuerberatung',
        'short_label' => 'Steinbach StB',
        'internal_code' => 'STEI',
    ]);
    Customer::factory()->create([
        'company_name' => 'Hansen Logistik GmbH',
        'short_label' => 'Hansen Logistik',
        'internal_code' => 'HANS',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->set('search', $suchbegriff)
        ->assertSee('Steinbach Steuerberatung')
        ->assertDontSee('Hansen Logistik GmbH');
})->with([
    'Kundennummer' => 'KD-00001',
    'Firmenname' => 'Steinbach',
    'Kurzbezeichnung' => 'StB',
    'Kuerzel' => 'STEI',
]);

it('findet Privatkunden ueber den Nachnamen', function (): void {
    Customer::factory()->privatePerson()->create([
        'first_name' => 'Helena',
        'last_name' => 'Roth',
        'short_label' => 'Helena Roth',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->set('search', 'Roth')
        ->assertSee('Helena Roth');
});

it('filtert nach Kundentyp', function (): void {
    Customer::factory()->create(['company_name' => 'Nordlicht Werbeagentur GmbH', 'short_label' => 'Nordlicht']);
    Customer::factory()->privatePerson()->create([
        'first_name' => 'Christoph',
        'last_name' => 'Bienert',
        'short_label' => 'Christoph Bienert',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->set('type', CustomerType::Private->value)
        ->assertSee('Christoph Bienert')
        ->assertDontSee('Nordlicht Werbeagentur GmbH');
});

it('filtert nach internem Verantwortlichen', function (): void {
    $verantwortlich = User::factory()->create(['name' => 'Sabine Wagner']);

    Customer::factory()->create([
        'company_name' => 'Betreute Firma GmbH',
        'short_label' => 'Betreut',
        'responsible_user_id' => $verantwortlich->id,
    ]);
    Customer::factory()->create(['company_name' => 'Andere Firma GmbH', 'short_label' => 'Andere']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->set('responsibleUserId', (string) $verantwortlich->id)
        ->assertSee('Betreute Firma GmbH')
        ->assertDontSee('Andere Firma GmbH');
});

it('setzt alle Filter zurueck', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->set('search', 'irgendwas')
        ->set('status', 'archived')
        ->set('type', CustomerType::Private->value)
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('status', 'active')
        ->assertSet('type', '');
});

it('zeigt standardmaessig den Kunden, seine geplanten Umsaetze und den Status', function (): void {
    $component = Livewire::actingAs(User::factory()->create())->test(CustomerList::class);

    $sichtbar = array_column($component->instance()->tableHeaders(), 'index');

    expect($sichtbar)->toBe([
        'customer', 'monthly_revenue', 'yearly_revenue', 'monthly_costs', 'margin', 'status',
    ]);
});

it('haelt die uebrigen Spalten zuschaltbar bereit', function (): void {
    $component = Livewire::actingAs(User::factory()->create())->test(CustomerList::class);

    expect(array_column($component->get('tableColumns'), 'key'))
        ->toContain('customer_number', 'internal_code', 'type', 'responsible', 'contact', 'active_services_count', 'activity')
        ->and($component->instance()->isColumnVisible('activity'))->toBeFalse();
});

it('blendet eine Spalte global aus und merkt sich das', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'monthly_costs');

    expect(TableConfiguration::query()->where('table_key', 'customers')->exists())->toBeTrue();

    // Auch fuer einen anderen Benutzer, denn die Konfiguration gilt global.
    $component = Livewire::actingAs(User::factory()->create())->test(CustomerList::class);

    expect($component->instance()->isColumnVisible('monthly_costs'))->toBeFalse();
});

it('blendet feste Spalten nicht aus', function (): void {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'customer');

    expect($component->instance()->isColumnVisible('customer'))->toBeTrue();
});

it('aendert die Spaltenreihenfolge', function (): void {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('moveColumn', 'yearly_revenue', -1);

    expect(array_column($component->get('tableColumns'), 'key')[1])->toBe('yearly_revenue');
});

it('setzt die Tabellenkonfiguration auf den Standard zurueck', function (): void {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'monthly_costs')
        ->call('resetTableConfiguration');

    expect(TableConfiguration::query()->where('table_key', 'customers')->exists())->toBeFalse()
        ->and($component->instance()->isColumnVisible('monthly_costs'))->toBeTrue();
});

it('zaehlt die Kunden je Statusfilter', function (): void {
    Customer::factory()->count(3)->create();
    Customer::factory()->archived()->count(2)->create();

    $filter = collect(
        Livewire::actingAs(User::factory()->create())
            ->test(CustomerList::class)
            ->instance()
            ->statusFilters()
    )->keyBy('wert');

    expect($filter['']['anzahl'])->toBe(5)
        ->and($filter['active']['anzahl'])->toBe(3)
        ->and($filter['archived']['anzahl'])->toBe(2);
});

it('beruecksichtigt Suche und Typ in den Zaehlern', function (): void {
    Customer::factory()->create(['company_name' => 'Nordlicht Werbeagentur GmbH', 'short_label' => 'Nordlicht']);
    Customer::factory()->count(4)->create();

    $filter = collect(
        Livewire::actingAs(User::factory()->create())
            ->test(CustomerList::class)
            ->set('search', 'Nordlicht')
            ->instance()
            ->statusFilters()
    )->keyBy('wert');

    expect($filter['']['anzahl'])->toBe(1);
});

it('wechselt den Status ueber die Schnellauswahl', function (): void {
    Customer::factory()->archived()->create(['company_name' => 'Altbestand GmbH', 'short_label' => 'Alt']);

    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('setStatus', 'archived')
        ->assertSet('status', 'archived')
        ->assertSee('Altbestand GmbH');
});

it('nennt den Leerzustand des Entwurfs', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->set('search', 'gibtesnicht')
        ->assertSee('Kein Kunde passt zu Filter und Suche.');
});

it('zeigt den Hauptansprechpartner in der Liste', function (): void {
    $kunde = Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH', 'short_label' => 'Müller']);

    app(CreateContact::class)(
        attributes: ['first_name' => 'Thomas', 'last_name' => 'Lindner'],
        assignments: [['customer_id' => $kunde->id, 'is_primary_contact' => true]],
        emails: [['email' => 'thomas.lindner@muller.example.de', 'type' => 'business', 'is_primary' => true]],
    );

    // Die Spalte ist seit dem Umbau zuschaltbar statt voreingestellt.
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'contact')
        ->assertSee('Thomas Lindner')
        ->assertSee('thomas.lindner@muller.example.de');
});

it('laedt den Hauptansprechpartner je Kunde, nicht nur einmal insgesamt', function (): void {
    $namen = ['Lindner', 'Neumann', 'Achenbach'];

    foreach ($namen as $index => $nachname) {
        $kunde = Customer::factory()->create([
            'company_name' => "Firma {$nachname} GmbH",
            'short_label' => $nachname,
        ]);

        app(CreateContact::class)(
            attributes: ['first_name' => 'Test', 'last_name' => $nachname],
            assignments: [['customer_id' => $kunde->id, 'is_primary_contact' => true]],
        );
    }

    $komponente = Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'contact');

    foreach ($namen as $nachname) {
        $komponente->assertSee("Test {$nachname}");
    }
});
