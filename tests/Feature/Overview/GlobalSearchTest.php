<?php

use App\Actions\Contacts\CreateContact;
use App\Actions\Search\GlobalSearch;
use App\Livewire\Search\GlobalSearchBar;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('durchsucht Kunden ueber Nummer, Name, Kurzbezeichnung und Kuerzel', function (string $suchbegriff): void {
    Customer::factory()->create([
        'company_name' => 'Müller Elektrotechnik GmbH',
        'short_label' => 'Müller Elektro',
        'internal_code' => 'MUEL',
    ]);

    $treffer = app(GlobalSearch::class)($suchbegriff)->firstWhere('typ', 'Kunden');

    expect($treffer['treffer']->pluck('name')->all())->toBe(['Müller Elektrotechnik GmbH']);
})->with([
    'Kundennummer' => 'KD-00001',
    'Firmenname' => 'Müller Elektro',
    'Kürzel' => 'MUEL',
]);

it('findet Kunden ueber ihre E-Mail-Adresse', function (): void {
    $customer = Customer::factory()->privatePerson()->create([
        'first_name' => 'Beate',
        'last_name' => 'Stolzenberg',
    ]);
    $customer->emailAddresses()->create([
        'email' => 'b.stolzenberg@example.de',
        'type' => 'private',
        'is_primary' => true,
    ]);

    $gruppen = app(GlobalSearch::class)('b.stolzenberg@');

    expect($gruppen->firstWhere('typ', 'Kunden')['treffer'])->toHaveCount(1);
});

it('findet Ansprechpartner ueber Name und E-Mail-Adresse', function (string $suchbegriff): void {
    app(CreateContact::class)(
        attributes: ['first_name' => 'Thomas', 'last_name' => 'Lindner'],
        assignments: [['customer_id' => Customer::factory()->create()->id]],
        emails: [['email' => 't.lindner@example.de', 'type' => 'business', 'is_primary' => true]],
    );

    $gruppen = app(GlobalSearch::class)($suchbegriff);

    expect($gruppen->firstWhere('typ', 'Ansprechpartner')['treffer']->pluck('name')->all())
        ->toBe(['Thomas Lindner']);
})->with([
    'Nachname' => 'Lindner',
    'E-Mail' => 't.lindner@',
]);

it('findet Katalogartikel und Kundenleistungen', function (): void {
    Product::factory()->create(['name' => 'Managed Hosting', 'internal_name' => 'managed-hosting']);
    CustomerService::factory()->create(['name' => 'Managed Hosting Müller']);

    $gruppen = app(GlobalSearch::class)('Managed Hosting');

    expect($gruppen->firstWhere('typ', 'Artikel / Leistungen')['treffer'])->toHaveCount(1)
        ->and($gruppen->firstWhere('typ', 'Kundenleistungen')['treffer'])->toHaveCount(1);
});

it('schliesst archivierte Datensaetze aus', function (): void {
    Customer::factory()->archived()->create(['company_name' => 'Archivierte Firma GmbH', 'short_label' => 'Archiv']);
    Product::factory()->archived()->create(['name' => 'Archivierter Artikel']);
    CustomerService::factory()->archived()->create(['name' => 'Archivierte Leistung']);

    expect(app(GlobalSearch::class)('Archiv'))->toBeEmpty();
});

it('sucht erst ab zwei Zeichen', function (): void {
    Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH', 'short_label' => 'Müller']);

    expect(app(GlobalSearch::class)('M'))->toBeEmpty()
        ->and(app(GlobalSearch::class)('Mü'))->not->toBeEmpty();
});

it('liefert je Typ höchstens fuenf Treffer', function (): void {
    Customer::factory()->count(8)->create(['company_name' => 'Testfirma GmbH', 'short_label' => 'Testfirma']);

    expect(app(GlobalSearch::class)('Testfirma')->firstWhere('typ', 'Kunden')['treffer'])->toHaveCount(5);
});

it('zeigt Treffer mit Typ, Name und Zusatzinformation an', function (): void {
    Customer::factory()->create([
        'company_name' => 'Müller Elektrotechnik GmbH',
        'short_label' => 'Müller Elektro',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(GlobalSearchBar::class)
        ->set('term', 'Müller')
        ->assertSee('Kunden')
        ->assertSee('Müller Elektrotechnik GmbH')
        ->assertSee('KD-00001');
});

it('blendet die Ergebnisse beim Schliessen wieder aus', function (): void {
    Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH', 'short_label' => 'Müller']);

    Livewire::actingAs(User::factory()->create())
        ->test(GlobalSearchBar::class)
        ->set('term', 'Müller')
        ->assertSet('showResults', true)
        ->call('close')
        ->assertSet('showResults', false)
        ->assertSet('term', '');
});
