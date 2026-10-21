<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\SaveProduct;
use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Category;
use App\Models\Product;
use App\Support\BillingInterval;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
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

    public string $purchase_cost_model = 'per_service';

    public string $purchase_usage_unit = 'user';

    public string $purchase_amount = '0,00';

    public string $purchase_currency = 'EUR';

    public string $purchase_eur_amount = '';

    public string $purchase_interval_unit = 'month';

    public int $purchase_interval_count = 1;

    public ?int $purchase_units_per_pack = null;

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
        $this->purchase_cost_model = $product->purchase_cost_model ?? 'per_service';
        $this->purchase_usage_unit = $product->purchase_usage_unit ?? 'user';
        $this->purchase_amount = Money::fromCents($product->purchase_amount_cents ?? 0)->toInput();
        $this->purchase_currency = $product->purchase_currency ?? 'EUR';
        $this->purchase_eur_amount = $product->purchase_eur_amount_cents === null ? '' : Money::fromCents($product->purchase_eur_amount_cents)->toInput();
        $this->purchase_interval_unit = $product->purchase_interval_unit?->value ?? 'month';
        $this->purchase_interval_count = $product->purchase_interval_count ?? 1;
        $this->purchase_units_per_pack = $product->purchase_units_per_pack;
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

    /**
     * Das Intervall, wie es vor der laufenden Aenderung galt.
     */
    private ?BillingInterval $intervalBeforeUpdate = null;

    public function updating(string $property, mixed $value): void
    {
        if ($this->isIntervalProperty($property)) {
            $this->intervalBeforeUpdate = $this->currentInterval();
        }
    }

    /**
     * Rechnet die Vorgabepreise auf das neue Intervall um.
     *
     * Wie bei der Kundenleistung gilt der Betrag je Abrechnungsperiode. Ohne
     * Umrechnung wuerde ein Wechsel von jaehrlich auf monatlich den Vorgabe-
     * preis verzwoelffachen — und von dort in jede neu angelegte Leistung
     * wandern.
     */
    public function updated(string $property, mixed $value): void
    {
        if (! $this->isIntervalProperty($property) || ! $this->intervalBeforeUpdate instanceof BillingInterval) {
            return;
        }

        $vorher = $this->intervalBeforeUpdate;
        $this->intervalBeforeUpdate = null;

        $nachher = $this->currentInterval();

        if ($nachher === null || $vorher->equals($nachher)) {
            return;
        }

        $this->default_purchase_price = $this->convertInput($this->default_purchase_price, $vorher, $nachher);
        $this->default_sales_price = $this->convertInput($this->default_sales_price, $vorher, $nachher);

        foreach ($this->components as $index => $component) {
            $this->components[$index]['purchase_price'] = $this->convertInput($component['purchase_price'], $vorher, $nachher);
            $this->components[$index]['sales_price'] = $this->convertInput($component['sales_price'], $vorher, $nachher);
        }
    }

    private function isIntervalProperty(string $property): bool
    {
        return in_array($property, ['default_billing_interval_unit', 'default_billing_interval_count'], true);
    }

    private function currentInterval(): ?BillingInterval
    {
        $einheit = BillingIntervalUnit::tryFrom($this->default_billing_interval_unit);

        if ($einheit === null) {
            return null;
        }

        if ($einheit->requiresCount() && $this->default_billing_interval_count < 1) {
            return null;
        }

        return BillingInterval::make($einheit, $this->default_billing_interval_count);
    }

    private function convertInput(string $eingabe, BillingInterval $von, BillingInterval $nach): string
    {
        if (trim($eingabe) === '') {
            return $eingabe;
        }

        try {
            $betrag = Money::fromEuroInput($eingabe);
        } catch (InvalidArgumentException) {
            // Unlesbares bleibt stehen: die Validierung beim Speichern meldet
            // es. Ein Intervallwechsel darf daran nicht abbrechen.
            return $eingabe;
        }

        return $von->convertTo($betrag, $nach)->toInput();
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
                'purchase_amount_cents' => Money::fromEuroInput($this->purchase_amount)->cents,
                'purchase_eur_amount_cents' => $this->purchase_currency === 'USD' && filled($this->purchase_eur_amount) ? Money::fromEuroInput($this->purchase_eur_amount)->cents : null,
                'purchase_units_per_pack' => $this->purchase_cost_model === 'shared_pack' ? $this->purchase_units_per_pack : null,
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
        ])->title($this->isEditing() ? 'Artikel bearbeiten' : 'Artikel anlegen');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'purchase_cost_model' => ['required', Rule::in(['per_service', 'per_unit', 'shared_unlimited', 'shared_pack'])],
            'purchase_usage_unit' => ['required', Rule::in(['user', 'customer', 'website', 'project'])],
            'purchase_amount' => ['required', 'string', $this->moneyRule()],
            'purchase_currency' => ['required', Rule::in(['EUR', 'USD'])],
            'purchase_eur_amount' => ['nullable', 'string', $this->moneyRule()],
            'purchase_interval_unit' => ['required', Rule::in(['month', 'year'])],
            'purchase_interval_count' => ['required', 'integer', 'min:1', 'max:999'],
            'purchase_units_per_pack' => ['required_if:purchase_cost_model,shared_pack', 'nullable', 'integer', 'min:1'],
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
            } catch (InvalidArgumentException) {
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
}
