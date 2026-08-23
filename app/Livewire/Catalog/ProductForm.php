<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\SaveProduct;
use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formular fuer Katalogartikel inklusive Tags und Leistungsbestandteilen.
 */
#[Layout('components.layouts.app')]
class ProductForm extends Component
{
    public ?Product $product = null;

    public string $name = '';

    public string $internal_name = '';

    public string $description = '';

    public string $category_id = '';

    public string $subcategory_id = '';

    public string $status = CatalogStatus::Active->value;

    public string $default_purchase_price = '0,00';

    public string $default_sales_price = '0,00';

    public string $default_billing_interval_unit = BillingIntervalUnit::Month->value;

    public int $default_billing_interval_count = 1;

    /** @var array<int, int|string> */
    public array $tagIds = [];

    /** @var array<int, array<string, mixed>> */
    public array $components = [];

    public function mount(?Product $product = null): void
    {
        if (! $product?->exists) {
            $this->addComponent();

            return;
        }

        $this->product = $product;
        $this->name = $product->name;
        $this->internal_name = $product->internal_name;
        $this->description = (string) $product->description;
        $this->category_id = (string) ($product->category_id ?? '');
        $this->subcategory_id = (string) ($product->subcategory_id ?? '');
        $this->status = $product->status->value;
        $this->default_purchase_price = $product->defaultPurchasePrice()->toInput();
        $this->default_sales_price = $product->defaultSalesPrice()->toInput();
        $this->default_billing_interval_unit = $product->default_billing_interval_unit->value;
        $this->default_billing_interval_count = $product->default_billing_interval_count ?? 1;
        $this->tagIds = $product->tags->pluck('id')->all();

        $this->components = $product->serviceComponents
            ->map(fn ($component): array => [
                'id' => $component->id,
                'title' => $component->title,
                'description' => (string) $component->description,
                'purchase_price' => $component->purchasePrice()?->toInput() ?? '',
                'sales_price' => $component->salesPrice()?->toInput() ?? '',
            ])
            ->all();

        $this->components === [] && $this->addComponent();
    }

    public function isEditing(): bool
    {
        return $this->product?->exists ?? false;
    }

    public function requiresIntervalCount(): bool
    {
        return BillingIntervalUnit::from($this->default_billing_interval_unit)->requiresCount();
    }

    public function updatedCategoryId(): void
    {
        // Die Unterkategorie muss zur gewaehlten Kategorie passen.
        $this->subcategory_id = '';
    }

    public function addComponent(): void
    {
        $this->components[] = [
            'id' => null,
            'title' => '',
            'description' => '',
            'purchase_price' => '',
            'sales_price' => '',
        ];
    }

    public function removeComponent(int $index): void
    {
        unset($this->components[$index]);
        $this->components = array_values($this->components);
    }

    public function moveComponent(int $index, int $offset): void
    {
        $target = $index + $offset;

        if ($target < 0 || $target >= count($this->components)) {
            return;
        }

        [$this->components[$index], $this->components[$target]] =
            [$this->components[$target], $this->components[$index]];
    }

    public function save(SaveProduct $saveProduct): void
    {
        $validated = $this->validate($this->rules(), attributes: $this->validationAttributes());

        $product = $saveProduct(
            attributes: [
                ...$validated,
                'category_id' => $this->category_id !== '' ? (int) $this->category_id : null,
                'subcategory_id' => $this->subcategory_id !== '' ? (int) $this->subcategory_id : null,
            ],
            tags: $this->tagIds,
            components: $this->components,
            product: $this->product,
        );

        session()->flash('erfolg', $this->isEditing()
            ? 'Artikel gespeichert.'
            : 'Artikel angelegt.');

        $this->redirectRoute('products.show', $product, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.catalog.product-form', [
            'statusOptions' => CatalogStatus::options(),
            'intervalUnitOptions' => BillingIntervalUnit::options(),
            'rootCategories' => $this->rootCategories(),
            'subcategories' => $this->subcategories(),
            'tags' => $this->tags(),
        ])->title($this->isEditing() ? 'Artikel bearbeiten' : 'Artikel anlegen');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'internal_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(CatalogStatus::values())],
            'default_purchase_price' => ['required', 'string', $this->moneyRule()],
            'default_sales_price' => ['required', 'string', $this->moneyRule()],
            'default_billing_interval_unit' => ['required', Rule::in(BillingIntervalUnit::values())],
            'default_billing_interval_count' => ['required', 'integer', 'min:1', 'max:999'],
            'components.*.title' => ['nullable', 'string', 'max:255'],
            'components.*.description' => ['nullable', 'string', 'max:1000'],
            'components.*.purchase_price' => ['nullable', 'string', $this->moneyRule()],
            'components.*.sales_price' => ['nullable', 'string', $this->moneyRule()],
        ];
    }

    /**
     * Prueft, ob eine Eingabe als Geldbetrag lesbar ist.
     */
    private function moneyRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            if (blank($value)) {
                return;
            }

            try {
                Money::fromEuroInput($value);
            } catch (\InvalidArgumentException) {
                $fail('Der Wert ist kein gültiger Geldbetrag.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'name' => 'Name',
            'internal_name' => 'Interne Bezeichnung',
            'description' => 'Beschreibung',
            'status' => 'Status',
            'default_purchase_price' => 'Standard-Einkaufspreis',
            'default_sales_price' => 'Standard-Verkaufspreis',
            'default_billing_interval_unit' => 'Abrechnungsintervall',
            'default_billing_interval_count' => 'Intervallanzahl',
            'components.*.title' => 'Titel des Leistungsbestandteils',
        ];
    }

    /**
     * @return Collection<int, Category>
     */
    private function rootCategories(): Collection
    {
        return Category::query()->roots()->active()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Category>
     */
    private function subcategories(): Collection
    {
        if ($this->category_id === '') {
            return collect();
        }

        return Category::query()
            ->where('parent_id', $this->category_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Tag>
     */
    private function tags(): Collection
    {
        return Tag::query()->orderBy('name')->get();
    }
}
