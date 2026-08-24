<?php

use App\Livewire\Catalog\CategoryList;
use App\Livewire\Catalog\ProductDetail;
use App\Livewire\Catalog\ProductForm;
use App\Livewire\Catalog\ProductList;
use App\Livewire\Catalog\TagList;
use App\Models\Category;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

it('legt einen Artikel ueber das Formular an', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ProductForm::class)
        ->set('name', 'Nextcloud Hosting')
        ->set('internal_name', 'nextcloud-hosting')
        ->set('default_purchase_price', '6,00')
        ->set('default_sales_price', '19,90')
        ->set('components.0.title', '100 GB Speicher')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $product = Product::query()->where('internal_name', 'nextcloud-hosting')->firstOrFail();

    expect($product->default_sales_price_cents)->toBe(1990)
        ->and($product->serviceComponents)->toHaveCount(1);
});

it('lehnt ungueltige Geldbetraege ab', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ProductForm::class)
        ->set('name', 'Kaputt')
        ->set('internal_name', 'kaputt')
        ->set('default_sales_price', 'keine Zahl')
        ->call('save')
        ->assertHasErrors('default_sales_price');
});

it('leert die Unterkategorie beim Wechsel der Kategorie', function (): void {
    $hosting = Category::factory()->create(['name' => 'Hosting']);
    $managed = Category::factory()->create(['name' => 'Managed Hosting', 'parent_id' => $hosting->id]);
    $cloud = Category::factory()->create(['name' => 'Cloud']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductForm::class)
        ->set('category_id', (string) $hosting->id)
        ->set('subcategory_id', (string) $managed->id)
        ->set('category_id', (string) $cloud->id)
        ->assertSet('subcategory_id', '');
});

it('durchsucht Artikel nach Name und interner Bezeichnung', function (string $suchbegriff): void {
    Product::factory()->create(['name' => 'Managed Hosting', 'internal_name' => 'managed-hosting']);
    Product::factory()->create(['name' => 'Webhosting Standard', 'internal_name' => 'webhosting-standard']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductList::class)
        ->set('search', $suchbegriff)
        ->assertSee('Managed Hosting')
        ->assertDontSee('Webhosting Standard');
})->with([
    'Name' => 'Managed',
    'interne Bezeichnung' => 'managed-hosting',
]);

it('filtert Artikel nach Kategorie inklusive Unterkategorie', function (): void {
    $hosting = Category::factory()->create(['name' => 'Hosting']);
    $managed = Category::factory()->create(['name' => 'Managed Hosting', 'parent_id' => $hosting->id]);
    $cloud = Category::factory()->create(['name' => 'Cloud']);

    Product::factory()->create([
        'name' => 'Managed Hosting Paket',
        'category_id' => $hosting->id,
        'subcategory_id' => $managed->id,
    ]);
    Product::factory()->create(['name' => 'Nextcloud Paket', 'category_id' => $cloud->id]);

    $component = Livewire::actingAs(User::factory()->create())->test(ProductList::class);

    $component->set('categoryId', (string) $hosting->id)
        ->assertSee('Managed Hosting Paket')
        ->assertDontSee('Nextcloud Paket');

    // Auch die Unterkategorie allein muss den Artikel finden.
    $component->set('categoryId', (string) $managed->id)
        ->assertSee('Managed Hosting Paket');
});

it('filtert Artikel nach Tag', function (): void {
    $tag = Tag::factory()->create(['name' => 'Managed']);

    $mitTag = Product::factory()->create(['name' => 'Managed Hosting']);
    $mitTag->tags()->attach($tag);

    Product::factory()->create(['name' => 'Webhosting Standard']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductList::class)
        ->set('tagId', (string) $tag->id)
        ->assertSee('Managed Hosting')
        ->assertDontSee('Webhosting Standard');
});

it('blendet archivierte Artikel standardmaessig aus', function (): void {
    Product::factory()->create(['name' => 'Aktives Hosting']);
    Product::factory()->archived()->create(['name' => 'Altes Hosting']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductList::class)
        ->assertSee('Aktives Hosting')
        ->assertDontSee('Altes Hosting');
});

it('legt eine Variante ueber die Detailseite an', function (): void {
    $product = Product::factory()->create(['default_sales_price_cents' => 5900]);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $product])
        ->call('createVariant')
        ->set('variantName', 'Business')
        ->set('variantSalesPrice', '59,00')
        ->set('variantComponents.0.title', '3 Websites')
        ->call('saveVariant')
        ->assertHasNoErrors();

    $variant = $product->variants()->firstOrFail();

    expect($variant->name)->toBe('Business')
        ->and($variant->sales_price_cents)->toBe(5900)
        ->and($variant->serviceComponents)->toHaveCount(1);
});

it('archiviert und reaktiviert eine Variante', function (): void {
    $product = Product::factory()->hasVariants(1)->create();
    $variant = $product->variants()->firstOrFail();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(ProductDetail::class, ['product' => $product]);

    $component->call('archiveVariant', $variant->id);
    expect($variant->fresh()->isArchived())->toBeTrue();

    $component->call('restoreVariant', $variant->id);
    expect($variant->fresh()->isArchived())->toBeFalse();
});

it('legt eine Kategorie und eine Unterkategorie ueber die Oberflaeche an', function (): void {
    $component = Livewire::actingAs(User::factory()->create())->test(CategoryList::class);

    $component->call('create')
        ->set('name', 'Hosting')
        ->call('save')
        ->assertHasNoErrors();

    $hosting = Category::query()->where('name', 'Hosting')->firstOrFail();

    $component->call('create', $hosting->id)
        ->set('name', 'Managed Hosting')
        ->call('save')
        ->assertHasNoErrors();

    expect($hosting->children()->count())->toBe(1);
});

it('meldet doppelte Kategorienamen im Formular', function (): void {
    Category::factory()->create(['name' => 'Hosting']);

    Livewire::actingAs(User::factory()->create())
        ->test(CategoryList::class)
        ->call('create')
        ->set('name', 'Hosting')
        ->call('save')
        ->assertHasErrors('name');
});

it('legt einen Tag an und lehnt Dubletten ab', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(TagList::class)
        ->call('create')
        ->set('name', 'Wartungsvertrag')
        ->set('color', 'blue')
        ->call('save')
        ->assertHasNoErrors();

    expect(Tag::query()->where('name', 'Wartungsvertrag')->exists())->toBeTrue();

    Livewire::actingAs(User::factory()->create())
        ->test(TagList::class)
        ->call('create')
        ->set('name', 'Wartungsvertrag')
        ->call('save')
        ->assertHasErrors('name');
});

it('zeigt standardmaessig die sechs Spalten des Entwurfs', function (): void {
    $komponente = Livewire::actingAs(User::factory()->create())->test(ProductList::class);

    expect(array_column($komponente->instance()->tableHeaders(), 'index'))->toBe([
        'article', 'category', 'interval', 'default_sales_price_cents', 'contracts', 'status',
    ]);
});

it('haelt Einkauf, Marge und Varianten zuschaltbar bereit', function (): void {
    $komponente = Livewire::actingAs(User::factory()->create())->test(ProductList::class);

    expect($komponente->instance()->isColumnVisible('margin'))->toBeFalse()
        ->and(array_column($komponente->get('tableColumns'), 'key'))
        ->toContain('default_purchase_price_cents', 'margin', 'variants_count');
});

it('bietet vorerst keine Tag-Spalte an', function (): void {
    $komponente = Livewire::actingAs(User::factory()->create())->test(ProductList::class);

    expect(array_column($komponente->get('tableColumns'), 'key'))->not->toContain('tags');
});

it('zaehlt die Artikel je Statusfilter', function (): void {
    Product::factory()->count(3)->create();
    Product::factory()->archived()->count(2)->create();

    $filter = collect(
        Livewire::actingAs(User::factory()->create())
            ->test(ProductList::class)
            ->instance()
            ->statusFilters()
    )->keyBy('wert');

    expect($filter['']['anzahl'])->toBe(5)
        ->and($filter['active']['anzahl'])->toBe(3)
        ->and($filter['archived']['anzahl'])->toBe(2);
});

it('zaehlt in der Kategorienleiste genau das, was der Klick zeigt', function (): void {
    $oberkategorie = Category::factory()->create(['name' => 'Hosting']);
    $unterkategorie = Category::factory()->for($oberkategorie, 'parent')->create(['name' => 'Webhosting']);

    Product::factory()->create(['category_id' => $oberkategorie->id]);
    Product::factory()->create(['category_id' => $oberkategorie->id, 'subcategory_id' => $unterkategorie->id]);

    $komponente = Livewire::actingAs(User::factory()->create())->test(ProductList::class);
    $kategorien = collect($komponente->viewData('categories'))->keyBy('name');

    // Ein Artikel in einer Unterkategorie traegt immer auch die Oberkategorie;
    // ein Aufsummieren der Kinder wuerde ihn dort doppelt zaehlen.
    expect($kategorien['Hosting']['anzahl'])->toBe(2)
        ->and($kategorien['Webhosting']['anzahl'])->toBe(1);

    // Der Zaehler muss halten, was der Klick einloest.
    foreach (['Hosting', 'Webhosting'] as $name) {
        $treffer = $komponente
            ->call('setCategory', (string) $kategorien[$name]['id'])
            ->viewData('products')
            ->total();

        expect($treffer)->toBe($kategorien[$name]['anzahl']);
    }
});

it('richtet die Kategoriezaehler am Statusfilter aus', function (): void {
    $kategorie = Category::factory()->create(['name' => 'Hosting']);

    Product::factory()->create(['category_id' => $kategorie->id]);
    Product::factory()->archived()->create(['category_id' => $kategorie->id]);

    $komponente = Livewire::actingAs(User::factory()->create())->test(ProductList::class);

    $aktiv = collect($komponente->viewData('categories'))->firstWhere('name', 'Hosting');

    $archiviert = collect(
        $komponente->call('setStatus', 'archived')->viewData('categories')
    )->firstWhere('name', 'Hosting');

    // Sonst stünde in der Leiste eine Zahl, die der Klick nicht einlöst.
    expect($aktiv['anzahl'])->toBe(1)
        ->and($archiviert['anzahl'])->toBe(1);
});

it('waehlt eine Kategorie ueber die Leiste', function (): void {
    $kategorie = Category::factory()->create(['name' => 'Hosting']);

    Product::factory()->create(['name' => 'Webhosting Standard', 'category_id' => $kategorie->id]);
    Product::factory()->create(['name' => 'Supportpauschale']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductList::class)
        ->call('setCategory', (string) $kategorie->id)
        ->assertSee('Webhosting Standard')
        ->assertDontSee('Supportpauschale');
});

it('nennt den Leerzustand des Entwurfs', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(ProductList::class)
        ->set('search', 'gibtesnicht')
        ->assertSee('Kein Artikel passt zu Kategorie und Suche.');
});

it('zaehlt die Kundenleistungen je Artikel', function (): void {
    $artikel = Product::factory()->create(['name' => 'Managed Hosting']);
    CustomerService::factory()->count(3)->for($artikel)->create();

    $komponente = Livewire::actingAs(User::factory()->create())->test(ProductList::class);

    expect($komponente->viewData('products')->firstWhere('name', 'Managed Hosting')->contracts_count)
        ->toBe(3);
});

it('zeigt keine Tags mehr in der Oberflaeche', function (): void {
    $artikel = Product::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Wartungsvertrag']);
    $artikel->tags()->attach($tag);

    $benutzer = User::factory()->create();

    Livewire::actingAs($benutzer)->test(ProductList::class)->assertDontSee('Wartungsvertrag');

    Livewire::actingAs($benutzer)
        ->test(ProductDetail::class, ['product' => $artikel])
        ->assertDontSee('Wartungsvertrag');
});
