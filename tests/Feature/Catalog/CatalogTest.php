<?php

use App\Actions\Catalog\ArchiveProduct;
use App\Actions\Catalog\SaveCategory;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductVariant;
use App\Actions\Catalog\SyncTags;
use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Validation\ValidationException;

it('legt einen Katalogartikel mit Standardpreisen an', function (): void {
    $product = app(SaveProduct::class)(attributes: [
        'name' => 'Managed Hosting',
        'internal_name' => 'managed-hosting',
        'description' => 'Vollständig betreutes Hosting.',
        'default_purchase_price' => '18,00',
        'default_sales_price' => '59,00',
        'default_billing_interval_unit' => BillingIntervalUnit::Month->value,
        'default_billing_interval_count' => 1,
    ]);

    expect($product->default_purchase_price_cents)->toBe(1800)
        ->and($product->default_sales_price_cents)->toBe(5900)
        ->and($product->status)->toBe(CatalogStatus::Active)
        ->and($product->defaultMargin()->cents)->toBe(4100)
        ->and($product->defaultMarginPercentage())->toBe(69.49)
        ->and($product->defaultBillingInterval()->label())->toBe('monatlich');
});

it('speichert Geldbetraege ausschliesslich als Integer in Cent', function (): void {
    $product = app(SaveProduct::class)(attributes: [
        'name' => 'SSL-Zertifikat',
        'internal_name' => 'ssl',
        'default_purchase_price' => '48,00',
        'default_sales_price' => '119,90',
        'default_billing_interval_unit' => BillingIntervalUnit::Year->value,
        'default_billing_interval_count' => 1,
    ]);

    expect($product->default_sales_price_cents)->toBeInt()->toBe(11990)
        ->and($product->getRawOriginal('default_sales_price_cents'))->toBe(11990);
});

it('setzt bei einmaligen Leistungen keine Intervallanzahl', function (): void {
    $product = app(SaveProduct::class)(attributes: [
        'name' => 'Webentwicklung',
        'internal_name' => 'webentwicklung',
        'default_purchase_price' => '0,00',
        'default_sales_price' => '110,00',
        'default_billing_interval_unit' => BillingIntervalUnit::Once->value,
        'default_billing_interval_count' => 5,
    ]);

    expect($product->default_billing_interval_count)->toBeNull()
        ->and($product->defaultBillingInterval()->isRecurring())->toBeFalse();
});

it('legt eine Kategorie mit Unterkategorie an', function (): void {
    $hosting = app(SaveCategory::class)(['name' => 'Hosting']);
    $managed = app(SaveCategory::class)(['name' => 'Managed Hosting', 'parent_id' => $hosting->id]);

    expect($hosting->isSubcategory())->toBeFalse()
        ->and($managed->isSubcategory())->toBeTrue()
        ->and($managed->fresh()->load('parent')->path())->toBe('Hosting → Managed Hosting');
});

it('erlaubt nur eine Unterebene', function (): void {
    $hosting = app(SaveCategory::class)(['name' => 'Hosting']);
    $managed = app(SaveCategory::class)(['name' => 'Managed Hosting', 'parent_id' => $hosting->id]);

    expect(fn () => app(SaveCategory::class)(['name' => 'Noch tiefer', 'parent_id' => $managed->id]))
        ->toThrow(ValidationException::class);
});

it('lehnt doppelte Kategorienamen auf derselben Ebene ab', function (): void {
    app(SaveCategory::class)(['name' => 'Hosting']);

    expect(fn () => app(SaveCategory::class)(['name' => 'Hosting']))
        ->toThrow(ValidationException::class);
});

it('erlaubt gleiche Namen in verschiedenen Kategorien', function (): void {
    $hosting = app(SaveCategory::class)(['name' => 'Hosting']);
    $cloud = app(SaveCategory::class)(['name' => 'Cloud']);

    app(SaveCategory::class)(['name' => 'Backup', 'parent_id' => $hosting->id]);
    app(SaveCategory::class)(['name' => 'Backup', 'parent_id' => $cloud->id]);

    expect(Category::query()->where('name', 'Backup')->count())->toBe(2);
});

it('lehnt eine Unterkategorie ab, die nicht zur Kategorie gehoert', function (): void {
    $hosting = app(SaveCategory::class)(['name' => 'Hosting']);
    $cloud = app(SaveCategory::class)(['name' => 'Cloud']);
    $nextcloud = app(SaveCategory::class)(['name' => 'Nextcloud', 'parent_id' => $cloud->id]);

    expect(fn () => app(SaveProduct::class)(attributes: [
        'name' => 'Falsche Zuordnung',
        'internal_name' => 'falsch',
        'category_id' => $hosting->id,
        'subcategory_id' => $nextcloud->id,
        'default_purchase_price' => '0,00',
        'default_sales_price' => '10,00',
        'default_billing_interval_unit' => BillingIntervalUnit::Month->value,
        'default_billing_interval_count' => 1,
    ]))->toThrow(ValidationException::class);
});

it('legt eine Variante an, die Werte vom Artikel erbt', function (): void {
    $product = Product::factory()->create([
        'default_purchase_price_cents' => 1800,
        'default_sales_price_cents' => 5900,
        'default_billing_interval_unit' => BillingIntervalUnit::Month,
        'default_billing_interval_count' => 1,
    ]);

    $variant = app(SaveProductVariant::class)($product, ['name' => 'Basic']);

    expect($variant->purchase_price_cents)->toBeNull()
        ->and($variant->overridesProductDefaults())->toBeFalse()
        ->and($variant->effectivePurchasePrice()->cents)->toBe(1800)
        ->and($variant->effectiveSalesPrice()->cents)->toBe(5900)
        ->and($variant->effectiveBillingInterval()->label())->toBe('monatlich');
});

it('legt eine Variante mit eigenen Werten an', function (): void {
    $product = Product::factory()->create([
        'default_purchase_price_cents' => 1800,
        'default_sales_price_cents' => 5900,
    ]);

    $variant = app(SaveProductVariant::class)($product, [
        'name' => 'Premium',
        'purchase_price' => '32,00',
        'sales_price' => '99,00',
        'billing_interval_unit' => BillingIntervalUnit::Year->value,
        'billing_interval_count' => 1,
    ]);

    expect($variant->overridesProductDefaults())->toBeTrue()
        ->and($variant->effectivePurchasePrice()->cents)->toBe(3200)
        ->and($variant->effectiveSalesPrice()->cents)->toBe(9900)
        ->and($variant->effectiveMargin()->cents)->toBe(6700)
        ->and($variant->effectiveBillingInterval()->label())->toBe('jährlich');
});

it('speichert Leistungsbestandteile in der uebergebenen Reihenfolge', function (): void {
    $product = app(SaveProduct::class)(
        attributes: [
            'name' => 'Managed Website',
            'internal_name' => 'managed-website',
            'default_purchase_price' => '0,00',
            'default_sales_price' => '99,00',
            'default_billing_interval_unit' => BillingIntervalUnit::Month->value,
            'default_billing_interval_count' => 1,
        ],
        components: [
            ['title' => 'Hosting'],
            ['title' => 'Tägliches Backup', 'description' => 'Aufbewahrung 30 Tage'],
            ['title' => 'Monitoring'],
            ['title' => '', 'description' => 'wird verworfen'],
            ['title' => '30 Minuten Support', 'sales_price' => '0,00', 'purchase_price' => '12,50'],
        ],
    );

    expect($product->serviceComponents)->toHaveCount(4)
        ->and($product->serviceComponents->pluck('title')->all())
        ->toBe(['Hosting', 'Tägliches Backup', 'Monitoring', '30 Minuten Support'])
        ->and($product->serviceComponents->last()->purchasePrice()->cents)->toBe(1250)
        ->and($product->serviceComponents->last()->salesPrice()->cents)->toBe(0)
        ->and($product->serviceComponents->first()->purchasePrice())->toBeNull();
});

it('haengt Tags an einen Artikel und legt neue dabei an', function (): void {
    $bestehend = Tag::factory()->create(['name' => 'Managed']);

    $product = app(SaveProduct::class)(
        attributes: [
            'name' => 'Webhosting',
            'internal_name' => 'webhosting',
            'default_purchase_price' => '2,50',
            'default_sales_price' => '7,90',
            'default_billing_interval_unit' => BillingIntervalUnit::Month->value,
            'default_billing_interval_count' => 1,
        ],
        tags: [$bestehend->id, 'Wartungsvertrag'],
    );

    expect($product->tags->pluck('name')->sort()->values()->all())->toBe(['Managed', 'Wartungsvertrag'])
        ->and(Tag::query()->where('name', 'Wartungsvertrag')->exists())->toBeTrue();
});

it('nutzt dieselbe Tag-Tabelle fuer Kunden und Artikel', function (): void {
    $tag = Tag::factory()->create(['name' => 'Referenzkunde']);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    app(SyncTags::class)($customer, [$tag->id]);
    app(SyncTags::class)($product, [$tag->id]);

    expect($tag->customers)->toHaveCount(1)
        ->and($tag->products)->toHaveCount(1)
        ->and($customer->tags->first()->name)->toBe('Referenzkunde');
});

it('archiviert einen Artikel samt Varianten', function (): void {
    $product = Product::factory()->hasVariants(3)->create();

    app(ArchiveProduct::class)($product);

    expect($product->isArchived())->toBeTrue()
        ->and($product->variants()->where('status', CatalogStatus::Archived)->count())->toBe(3);
});

it('laesst bestehende Verweise auf archivierte Artikel bestehen', function (): void {
    $product = Product::factory()->create();

    app(ArchiveProduct::class)($product);

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and(Product::query()->active()->count())->toBe(0);
});
