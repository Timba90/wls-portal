<?php

use App\Livewire\Archive\ArchivePage;
use App\Livewire\Services\ServiceOverview;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

it('zeigt Leistungen aller Kunden mit Preisen', function (): void {
    $customer = Customer::factory()->create(['company_name' => 'Müller Elektrotechnik GmbH', 'short_label' => 'Müller']);

    CustomerService::factory()->for($customer)->create([
        'name' => 'Managed Hosting Müller',
        'purchase_price_cents' => 1800,
        'sales_price_cents' => 5900,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(ServiceOverview::class)
        ->assertSee('Managed Hosting Müller')
        ->assertSee('Müller')
        ->assertSee('59,00 €')
        ->assertSee('18,00 €')
        ->assertSee('41,00 €');
});

it('summiert ueber alle gefilterten Leistungen, nicht nur ueber die Seite', function (): void {
    CustomerService::factory()->count(30)->create(['purchase_price_cents' => 0, 'sales_price_cents' => 1000]);

    $component = Livewire::actingAs(User::factory()->create())->test(ServiceOverview::class);

    expect($component->viewData('summe')['monthlyRevenue']->cents)->toBe(30000)
        ->and($component->viewData('summe')['count'])->toBe(30);
});

it('durchsucht Leistung, Rechnungsbezeichnung und Kunde', function (string $suchbegriff): void {
    $customer = Customer::factory()->create(['company_name' => 'Nordlicht Werbeagentur GmbH', 'short_label' => 'Nordlicht']);

    CustomerService::factory()->for($customer)->create([
        'name' => 'Managed Hosting Nordlicht',
        'billing_label' => 'Hostingpaket Business',
    ]);
    CustomerService::factory()->create(['name' => 'Ganz andere Leistung']);

    Livewire::actingAs(User::factory()->create())
        ->test(ServiceOverview::class)
        ->set('search', $suchbegriff)
        ->assertSee('Managed Hosting Nordlicht')
        ->assertDontSee('Ganz andere Leistung');
})->with([
    'Leistungsname' => 'Managed Hosting Nordlicht',
    'Rechnungsbezeichnung' => 'Hostingpaket',
    'Kundenname' => 'Nordlicht',
]);

it('filtert nach Status, Katalogartikel, Kategorie, Tag und Verantwortlichem', function (): void {
    $product = Product::factory()->create(['name' => 'Managed Hosting']);
    $category = Category::factory()->create(['name' => 'Hosting']);
    $tag = Tag::factory()->create(['name' => 'Managed']);
    $user = User::factory()->create(['name' => 'Sabine Wagner']);

    $treffer = CustomerService::factory()->create([
        'name' => 'Passende Leistung',
        'product_id' => $product->id,
        'category_id' => $category->id,
        'responsible_user_id' => $user->id,
    ]);
    $treffer->tags()->attach($tag);

    CustomerService::factory()->create(['name' => 'Andere Leistung']);

    $component = Livewire::actingAs(User::factory()->create())->test(ServiceOverview::class);

    foreach ([
        ['productId', (string) $product->id],
        ['categoryId', (string) $category->id],
        ['tagId', (string) $tag->id],
        ['responsibleUserId', (string) $user->id],
    ] as [$feld, $wert]) {
        $component->call('resetFilters')
            ->set($feld, $wert)
            ->assertSee('Passende Leistung')
            ->assertDontSee('Andere Leistung');
    }
});

it('filtert nach Abrechnungsstatus', function (): void {
    CustomerService::factory()->create(['name' => 'Wird abgerechnet']);
    CustomerService::factory()->doNotBill()->create(['name' => 'Bewusst nicht abrechnen']);
    CustomerService::factory()->oneTime()->create(['name' => 'Einmalige Leistung']);

    $component = Livewire::actingAs(User::factory()->create())->test(ServiceOverview::class);

    $component->set('billingFilter', 'do_not_bill')
        ->assertSee('Bewusst nicht abrechnen')
        ->assertDontSee('Wird abgerechnet');

    $component->set('billingFilter', 'once')
        ->assertSee('Einmalige Leistung')
        ->assertDontSee('Wird abgerechnet');
});

it('zeigt archivierte Leistungen nur im Archiv', function (): void {
    CustomerService::factory()->archived()->create(['name' => 'Archivierte Leistung']);

    Livewire::actingAs(User::factory()->create())
        ->test(ServiceOverview::class)
        ->assertDontSee('Archivierte Leistung');

    Livewire::actingAs(User::factory()->create())
        ->test(ArchivePage::class)
        ->set('tab', 'leistungen')
        ->assertSee('Archivierte Leistung');
});

it('zeigt archivierte Kunden, Ansprechpartner und Artikel im Archiv', function (): void {
    Customer::factory()->archived()->create(['company_name' => 'Archivierte Firma GmbH', 'short_label' => 'Archiv']);
    Contact::factory()->archived()->create(['first_name' => 'Archivierter', 'last_name' => 'Kontakt']);
    Product::factory()->archived()->create(['name' => 'Archivierter Artikel']);

    $component = Livewire::actingAs(User::factory()->create())->test(ArchivePage::class);

    $component->assertSee('Archivierte Firma GmbH');
    $component->set('tab', 'ansprechpartner')->assertSee('Archivierter Kontakt');
    $component->set('tab', 'artikel')->assertSee('Archivierter Artikel');
});

it('durchsucht das Archiv', function (): void {
    Customer::factory()->archived()->create(['company_name' => 'Gesuchte Firma GmbH', 'short_label' => 'Gesucht']);
    Customer::factory()->archived()->create(['company_name' => 'Andere Firma GmbH', 'short_label' => 'Andere']);

    Livewire::actingAs(User::factory()->create())
        ->test(ArchivePage::class)
        ->set('search', 'Gesuchte')
        ->assertSee('Gesuchte Firma GmbH')
        ->assertDontSee('Andere Firma GmbH');
});

it('zeigt die Anzahl je Archivbereich', function (): void {
    Customer::factory()->count(2)->archived()->create();
    Product::factory()->archived()->create();

    $counts = Livewire::actingAs(User::factory()->create())
        ->test(ArchivePage::class)
        ->viewData('counts');

    expect($counts['kunden'])->toBe(2)
        ->and($counts['artikel'])->toBe(1)
        ->and($counts['ansprechpartner'])->toBe(0);
});

it('kennzeichnet den Abrechnungsstatus in der Uebersicht', function (): void {
    CustomerService::factory()->create(['name' => 'Laufende Leistung']);
    CustomerService::factory()->doNotBill()->create(['name' => 'Kulanzleistung']);

    Livewire::actingAs(User::factory()->create())
        ->test(ServiceOverview::class)
        ->set('status', '')
        ->assertSee('Wird abgerechnet')
        ->assertSee('Inklusive');
});

it('zeigt voreingestellt die sieben Spalten der Rastertabelle', function (): void {
    $sichtbar = collect(Livewire::actingAs(User::factory()->create())
        ->test(ServiceOverview::class)
        ->instance()
        ->tableHeaders())
        ->pluck('index')
        ->all();

    expect($sichtbar)->toBe([
        'customer', 'name', 'interval', 'sales_price_cents', 'monthly', 'status', 'billing',
    ]);
});

it('haelt Katalogartikel, Marge und Einkauf zuschaltbar bereit', function (): void {
    $komponente = Livewire::actingAs(User::factory()->create())->test(ServiceOverview::class);

    // Erst unsichtbar, nach dem Zuschalten in der Kopfzeile.
    expect($komponente->instance()->isColumnVisible('margin'))->toBeFalse();

    $komponente->call('toggleColumn', 'margin');

    expect(collect($komponente->instance()->tableHeaders())->pluck('index'))->toContain('margin');
});

it('gibt jeder sichtbaren Spalte einen Rasteranteil', function (): void {
    $komponente = Livewire::actingAs(User::factory()->create())->test(ServiceOverview::class)->instance();
    $raster = $komponente->columnLayout();

    // Ohne Anteil fiele die Spalte im Raster auf 1fr zurück und das Verhältnis
    // zur Kopfzeile stimmte nicht mehr.
    foreach ($komponente->columnSettings() as $spalte) {
        expect($raster)->toHaveKey($spalte['key']);
    }
});
