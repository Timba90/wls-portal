<?php

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

it('zeigt standardmaessig alle Spalten', function (): void {
    $component = Livewire::actingAs(User::factory()->create())->test(CustomerList::class);

    expect(array_column($component->get('tableColumns'), 'key'))
        ->toContain('customer_number', 'name', 'short_label', 'internal_code', 'status', 'active_services_count', 'margin');
});

it('blendet eine Spalte global aus und merkt sich das', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'internal_code');

    expect(TableConfiguration::query()->where('table_key', 'customers')->exists())->toBeTrue();

    // Auch fuer einen anderen Benutzer, denn die Konfiguration gilt global.
    $component = Livewire::actingAs(User::factory()->create())->test(CustomerList::class);

    expect($component->instance()->isColumnVisible('internal_code'))->toBeFalse();
});

it('blendet feste Spalten nicht aus', function (): void {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'customer_number');

    expect($component->instance()->isColumnVisible('customer_number'))->toBeTrue();
});

it('aendert die Spaltenreihenfolge', function (): void {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('moveColumn', 'short_label', -1);

    expect(array_column($component->get('tableColumns'), 'key')[1])->toBe('short_label');
});

it('setzt die Tabellenkonfiguration auf den Standard zurueck', function (): void {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(CustomerList::class)
        ->call('toggleColumn', 'internal_code')
        ->call('resetTableConfiguration');

    expect(TableConfiguration::query()->where('table_key', 'customers')->exists())->toBeFalse()
        ->and($component->instance()->isColumnVisible('internal_code'))->toBeTrue();
});
