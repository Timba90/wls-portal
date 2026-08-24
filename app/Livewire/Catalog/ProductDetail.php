<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\ArchiveProduct;
use App\Actions\Catalog\RestoreProduct;
use App\Actions\Catalog\SaveProductVariant;
use App\Actions\Services\FindServicesWithCatalogChanges;
use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\AuditLog;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Detailseite eines Katalogartikels inklusive Variantenverwaltung.
 */
#[Layout('components.layouts.app')]
class ProductDetail extends Component
{
    public Product $product;

    #[Url(as: 'bereich', except: 'uebersicht')]
    public string $tab = 'uebersicht';

    public bool $showVariantForm = false;

    public ?int $editingVariantId = null;

    public string $variantName = '';

    public string $variantDescription = '';

    public string $variantPurchasePrice = '';

    public string $variantSalesPrice = '';

    public string $variantIntervalUnit = '';

    public int $variantIntervalCount = 1;

    public int $variantSortOrder = 0;

    public string $variantStatus = CatalogStatus::Active->value;

    /** @var array<int, array<string, mixed>> */
    public array $variantComponents = [];

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function createVariant(): void
    {
        $this->resetVariantForm();
        $this->variantSortOrder = ($this->product->variants()->max('sort_order') ?? 0) + 10;
        $this->showVariantForm = true;
    }

    public function editVariant(int $variantId): void
    {
        $variant = $this->product->variants()->with('serviceComponents')->findOrFail($variantId);

        $this->resetVariantForm();
        $this->editingVariantId = $variant->id;
        $this->variantName = $variant->name;
        $this->variantDescription = (string) $variant->description;
        $this->variantPurchasePrice = is_null($variant->purchase_price_cents)
            ? ''
            : Money::fromCents($variant->purchase_price_cents)->toInput();
        $this->variantSalesPrice = is_null($variant->sales_price_cents)
            ? ''
            : Money::fromCents($variant->sales_price_cents)->toInput();
        $this->variantIntervalUnit = $variant->billing_interval_unit?->value ?? '';
        $this->variantIntervalCount = $variant->billing_interval_count ?? 1;
        $this->variantSortOrder = $variant->sort_order;
        $this->variantStatus = $variant->status->value;

        $this->variantComponents = $variant->serviceComponents
            ->map(fn ($component): array => [
                'id' => $component->id,
                'title' => $component->title,
                'description' => (string) $component->description,
                'purchase_price' => $component->purchasePrice()?->toInput() ?? '',
                'sales_price' => $component->salesPrice()?->toInput() ?? '',
            ])
            ->all();

        $this->variantComponents === [] && $this->addVariantComponent();
        $this->showVariantForm = true;
    }

    public function addVariantComponent(): void
    {
        $this->variantComponents[] = [
            'id' => null,
            'title' => '',
            'description' => '',
            'purchase_price' => '',
            'sales_price' => '',
        ];
    }

    public function removeVariantComponent(int $index): void
    {
        unset($this->variantComponents[$index]);
        $this->variantComponents = array_values($this->variantComponents);
    }

    public function saveVariant(SaveProductVariant $saveProductVariant): void
    {
        $this->validate([
            'variantName' => ['required', 'string', 'max:255'],
            'variantDescription' => ['nullable', 'string', 'max:5000'],
            'variantPurchasePrice' => ['nullable', 'string'],
            'variantSalesPrice' => ['nullable', 'string'],
            'variantIntervalUnit' => ['nullable', Rule::in(BillingIntervalUnit::values())],
            'variantIntervalCount' => ['required', 'integer', 'min:1', 'max:999'],
            'variantSortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            'variantStatus' => ['required', Rule::in(CatalogStatus::values())],
            'variantComponents.*.title' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'variantName' => 'Name',
            'variantSortOrder' => 'Sortierung',
            'variantIntervalCount' => 'Intervallanzahl',
        ]);

        $saveProductVariant(
            product: $this->product,
            attributes: [
                'name' => $this->variantName,
                'description' => $this->variantDescription ?: null,
                'purchase_price' => $this->variantPurchasePrice,
                'sales_price' => $this->variantSalesPrice,
                'billing_interval_unit' => $this->variantIntervalUnit,
                'billing_interval_count' => $this->variantIntervalCount,
                'sort_order' => $this->variantSortOrder,
                'status' => $this->variantStatus,
            ],
            components: $this->variantComponents,
            variant: $this->editingVariantId
                ? $this->product->variants()->findOrFail($this->editingVariantId)
                : null,
        );

        $this->showVariantForm = false;
        $this->resetVariantForm();
        $this->product->unsetRelation('variants');

        $this->dispatch('variante-gespeichert');
    }

    public function archiveVariant(int $variantId): void
    {
        $this->product->variants()
            ->whereKey($variantId)
            ->each(fn (ProductVariant $variant) => $variant->forceFill([
                'status' => CatalogStatus::Archived,
                'archived_at' => now(),
            ])->save());

        $this->product->unsetRelation('variants');

        $this->dispatch('variante-archiviert');
    }

    public function restoreVariant(int $variantId): void
    {
        $this->product->variants()
            ->whereKey($variantId)
            ->each(fn (ProductVariant $variant) => $variant->forceFill([
                'status' => CatalogStatus::Active,
                'archived_at' => null,
            ])->save());

        $this->product->unsetRelation('variants');

        $this->dispatch('variante-reaktiviert');
    }

    public function archive(ArchiveProduct $archiveProduct): void
    {
        $archiveProduct($this->product);

        $this->product->refresh();

        $this->dispatch('artikel-archiviert');
    }

    public function restore(RestoreProduct $restoreProduct): void
    {
        $restoreProduct($this->product);

        $this->product->refresh();

        $this->dispatch('artikel-reaktiviert');
    }

    /**
     * Kundenleistungen, die auf diesem Katalogartikel beruhen.
     *
     * @return Collection<int, CustomerService>
     */
    public function usage(): Collection
    {
        return CustomerService::query()
            ->with('customer')
            ->where('product_id', $this->product->id)
            ->orderByRaw("field(status, 'active', 'planned', 'paused', 'ended', 'archived')")
            ->orderBy('name')
            ->get();
    }

    /**
     * Preisentwicklung des Listenpreises.
     *
     * Ein Katalogartikel hat keinen eigenen Preisverlauf — den fuehrt das
     * Projekt nur je Kundenleistung. Die Aenderungen am Listenpreis stehen
     * aber in der Aenderungshistorie, und die ist unveraenderlich. Von dort
     * kommen die Eintraege.
     *
     * @return Collection<int, array{zeitpunkt: ?Carbon, feld: string, alt: ?Money, neu: Money, benutzer: ?string}>
     */
    public function priceHistory(): Collection
    {
        $felder = [
            'default_sales_price_cents' => 'Verkaufspreis',
            'default_purchase_price_cents' => 'Einkaufspreis',
        ];

        return $this->product->auditLogs()
            ->with('user')
            ->get()
            ->flatMap(function (AuditLog $eintrag) use ($felder): array {
                $neu = $eintrag->new_values ?? [];
                $alt = $eintrag->old_values ?? [];

                return collect($felder)
                    ->filter(fn (string $label, string $feld): bool => array_key_exists($feld, $neu))
                    ->map(fn (string $label, string $feld): array => [
                        'zeitpunkt' => $eintrag->created_at,
                        'feld' => $label,
                        'alt' => array_key_exists($feld, $alt) ? Money::fromCents((int) $alt[$feld]) : null,
                        'neu' => Money::fromCents((int) $neu[$feld]),
                        'benutzer' => $eintrag->user?->name,
                    ])
                    ->values()
                    ->all();
            })
            ->values();
    }

    /**
     * Stammdaten fuer die rechte Spalte, in der Reihenfolge des Entwurfs.
     *
     * @return array<string, ?string>
     */
    public function masterData(): array
    {
        $intervall = $this->product->defaultBillingInterval();

        return [
            'Interner Name' => $this->product->internal_name,
            'Kategorie' => $this->product->category?->name,
            'Unterkategorie' => $this->product->subcategory?->name,
            'Turnus' => $intervall->label(),
            'Einkaufspreis' => $this->product->defaultPurchasePrice()->format(),
            'Verkaufspreis' => $this->product->defaultSalesPrice()->format(),
            'Varianten' => (string) $this->product->variants->count(),
            'Angelegt' => $this->product->created_at?->format('d.m.Y'),
            'Zuletzt geändert' => $this->product->updated_at?->format('d.m.Y H:i'),
        ];
    }

    public function render(): View
    {
        $this->product->load(['category', 'subcategory', 'tags', 'serviceComponents', 'variants.serviceComponents']);

        return view('livewire.catalog.product-detail', [
            'statusOptions' => CatalogStatus::options(),
            'intervalUnitOptions' => BillingIntervalUnit::options(),
            'verwendung' => $this->usage(),
            'veraltet' => app(FindServicesWithCatalogChanges::class)->forProduct($this->product->getKey()),
            'preisverlauf' => $this->priceHistory(),
        ])->title($this->product->name);
    }

    private function resetVariantForm(): void
    {
        $this->reset(
            'editingVariantId',
            'variantName',
            'variantDescription',
            'variantPurchasePrice',
            'variantSalesPrice',
            'variantIntervalUnit',
            'variantIntervalCount',
            'variantSortOrder',
            'variantStatus',
            'variantComponents',
        );

        $this->resetValidation();
        $this->addVariantComponent();
    }
}
