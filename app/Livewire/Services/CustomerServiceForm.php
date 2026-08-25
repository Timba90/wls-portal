<?php

namespace App\Livewire\Services;

use App\Actions\Services\CreateCustomerService;
use App\Actions\Services\UpdateCustomerService;
use App\Enums\BillingIntervalUnit;
use App\Enums\CustomerServiceStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ServiceComponent;
use App\Models\User;
use App\Support\BillingInterval;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formular fuer Kundenleistungen.
 *
 * Eine Leistung darf auf einem Katalogartikel beruhen oder vollstaendig
 * individuell sein. Wird ein Artikel gewaehlt, werden dessen Werte als Vorschlag
 * uebernommen — abweichen ist ausdruecklich erlaubt.
 */
#[Layout('components.layouts.app')]
class CustomerServiceForm extends Component
{
    public Customer $customer;

    public ?CustomerService $service = null;

    public string $product_id = '';

    public string $product_variant_id = '';

    public string $name = '';

    public string $billing_label = '';

    public string $description = '';

    public string $status = CustomerServiceStatus::Planned->value;

    public string $purchase_price = '0,00';

    public string $sales_price = '0,00';

    public string $billing_interval_unit = BillingIntervalUnit::Month->value;

    public int $billing_interval_count = 1;

    public string $service_start_date = '';

    public string $billing_start_date = '';

    public string $first_billing_date = '';

    public string $category_id = '';

    public string $subcategory_id = '';

    public string $responsible_user_id = '';

    /** @var array<int, int|string> */
    public array $tagIds = [];

    /** @var array<int, array<string, mixed>> */
    public array $components = [];

    public function mount(Customer $customer, ?CustomerService $service = null): void
    {
        $this->customer = $customer;

        if (! $service?->exists) {
            $this->responsible_user_id = (string) ($customer->responsible_user_id ?? '');
            $this->addComponent();

            return;
        }

        abort_unless($service->customer_id === $customer->getKey(), 404);
        abort_if($service->isArchived(), 403, 'Archivierte Kundenleistungen können nicht bearbeitet werden.');

        $this->service = $service;
        $this->product_id = (string) ($service->product_id ?? '');
        $this->product_variant_id = (string) ($service->product_variant_id ?? '');
        $this->name = $service->name;
        $this->billing_label = (string) $service->billing_label;
        $this->description = (string) $service->description;
        $this->status = $service->status->value;
        $this->purchase_price = $service->purchasePrice()->toInput();
        $this->sales_price = $service->salesPrice()->toInput();
        $this->billing_interval_unit = $service->billing_interval_unit->value;
        $this->billing_interval_count = $service->billing_interval_count ?? 1;
        $this->service_start_date = $service->service_start_date?->format('Y-m-d') ?? '';
        $this->billing_start_date = $service->billing_start_date?->format('Y-m-d') ?? '';
        $this->first_billing_date = $service->first_billing_date?->format('Y-m-d') ?? '';
        $this->category_id = (string) ($service->category_id ?? '');
        $this->subcategory_id = (string) ($service->subcategory_id ?? '');
        $this->responsible_user_id = (string) ($service->responsible_user_id ?? '');
        $this->tagIds = $service->tags->pluck('id')->all();

        $this->components = $service->serviceComponents
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
        return $this->service?->exists ?? false;
    }

    /**
     * Das Intervall, wie es vor der laufenden Aenderung galt.
     *
     * Wird in `updating()` festgehalten und in `updated()` gebraucht — beides
     * geschieht innerhalb derselben Anfrage.
     */
    private ?BillingInterval $intervalBeforeUpdate = null;

    /**
     * Merkt sich das bisherige Intervall, bevor Livewire den neuen Wert setzt.
     */
    public function updating(string $property, mixed $value): void
    {
        if ($this->isIntervalProperty($property)) {
            $this->intervalBeforeUpdate = $this->currentInterval();
        }
    }

    /**
     * Rechnet die Preise auf das neue Intervall um.
     *
     * Ein Einkaufspreis von 15,00 EUR im Jahr ist monatlich 1,25 EUR. Ohne
     * diese Umrechnung wuerde der Wechsel des Intervalls den Preis
     * stillschweigend verzwoelffachen — der Betrag im Feld gilt je
     * Abrechnungsperiode.
     */
    public function updated(string $property, mixed $value): void
    {
        if (! $this->isIntervalProperty($property) || ! $this->intervalBeforeUpdate instanceof BillingInterval) {
            return;
        }

        $vorher = $this->intervalBeforeUpdate;
        $this->intervalBeforeUpdate = null;

        $nachher = $this->currentInterval();

        if ($nachher === null || (string) $vorher === (string) $nachher) {
            return;
        }

        $this->purchase_price = $this->convertInput($this->purchase_price, $vorher, $nachher);
        $this->sales_price = $this->convertInput($this->sales_price, $vorher, $nachher);

        // Die Bestandteile tragen Anteile desselben Preises und folgen ihm.
        foreach ($this->components as $index => $component) {
            $this->components[$index]['purchase_price'] = $this->convertInput($component['purchase_price'], $vorher, $nachher);
            $this->components[$index]['sales_price'] = $this->convertInput($component['sales_price'], $vorher, $nachher);
        }
    }

    private function isIntervalProperty(string $property): bool
    {
        return in_array($property, ['billing_interval_unit', 'billing_interval_count'], true);
    }

    /**
     * Das eingestellte Intervall; `null`, solange die Eingabe unbrauchbar ist.
     */
    private function currentInterval(): ?BillingInterval
    {
        $einheit = BillingIntervalUnit::tryFrom($this->billing_interval_unit);

        if ($einheit === null) {
            return null;
        }

        if ($einheit->requiresCount() && $this->billing_interval_count < 1) {
            return null;
        }

        return BillingInterval::make($einheit, $this->billing_interval_count);
    }

    /**
     * Rechnet einen Betrag aus einem Eingabefeld um und gibt ihn wieder als
     * Eingabe zurueck. Leere Felder bleiben leer.
     */
    private function convertInput(string $eingabe, BillingInterval $von, BillingInterval $nach): string
    {
        if (trim($eingabe) === '') {
            return $eingabe;
        }

        return $von->convertTo(Money::fromEuroInput($eingabe), $nach)->toInput();
    }

    public function requiresIntervalCount(): bool
    {
        return BillingIntervalUnit::from($this->billing_interval_unit)->requiresCount();
    }

    /**
     * Uebernimmt die Werte des gewaehlten Katalogartikels als Vorschlag.
     */
    public function updatedProductId(): void
    {
        $this->product_variant_id = '';

        if ($this->product_id === '') {
            return;
        }

        $product = Product::query()->with('serviceComponents')->find($this->product_id);

        if (! $product) {
            return;
        }

        $this->applyCatalogDefaults(
            $product->default_purchase_price_cents,
            $product->default_sales_price_cents,
            $product->defaultBillingInterval()->unit,
            $product->defaultBillingInterval()->count,
            $product->name,
            $product->category_id,
            $product->subcategory_id,
            $product->serviceComponents,
        );
    }

    public function updatedProductVariantId(): void
    {
        if ($this->product_variant_id === '') {
            return;
        }

        $variant = ProductVariant::query()->with(['product', 'serviceComponents'])->find($this->product_variant_id);

        if (! $variant) {
            return;
        }

        $interval = $variant->effectiveBillingInterval();

        $this->applyCatalogDefaults(
            $variant->effectivePurchasePrice()->cents,
            $variant->effectiveSalesPrice()->cents,
            $interval->unit,
            $interval->count,
            "{$variant->product->name} {$variant->name}",
            $variant->product->category_id,
            $variant->product->subcategory_id,
            $variant->serviceComponents->isNotEmpty()
                ? $variant->serviceComponents
                : $variant->product->serviceComponents,
        );
    }

    public function updatedCategoryId(): void
    {
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

    public function save(
        CreateCustomerService $createCustomerService,
        UpdateCustomerService $updateCustomerService,
    ): void {
        $validated = $this->validate($this->rules(), attributes: $this->validationAttributes());

        $attributes = [
            ...$validated,
            'product_id' => $this->product_id !== '' ? (int) $this->product_id : null,
            'product_variant_id' => $this->product_variant_id !== '' ? (int) $this->product_variant_id : null,
            'billing_label' => $validated['billing_label'] ?: null,
            'description' => $validated['description'] ?: null,
            'service_start_date' => $validated['service_start_date'] ?: null,
            'billing_start_date' => $validated['billing_start_date'] ?: null,
            'first_billing_date' => $validated['first_billing_date'] ?: null,
            'category_id' => $this->category_id !== '' ? (int) $this->category_id : null,
            'subcategory_id' => $this->subcategory_id !== '' ? (int) $this->subcategory_id : null,
            'responsible_user_id' => $this->responsible_user_id !== '' ? (int) $this->responsible_user_id : null,
        ];

        $service = $this->isEditing()
            ? $updateCustomerService($this->service, $attributes, $this->tagIds, $this->components)
            : $createCustomerService($this->customer, $attributes, $this->tagIds, $this->components);

        session()->flash('erfolg', $this->isEditing()
            ? 'Kundenleistung gespeichert.'
            : 'Kundenleistung angelegt.');

        $this->redirectRoute('customer-services.show', [$this->customer, $service], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.services.customer-service-form', [
            'statusOptions' => CustomerServiceStatus::options(CustomerServiceStatus::selectable()),
            'intervalUnitOptions' => BillingIntervalUnit::options(),
            'products' => $this->products(),
            'variants' => $this->variants(),
            'rootCategories' => $this->rootCategories(),
            'subcategories' => $this->subcategories(),
            'responsibleUsers' => $this->responsibleUsers(),
        ])->title($this->isEditing() ? 'Kundenleistung bearbeiten' : 'Kundenleistung anlegen');
    }

    /**
     * @param  Collection<int, ServiceComponent>  $components
     */
    private function applyCatalogDefaults(
        int $purchaseCents,
        int $salesCents,
        BillingIntervalUnit $unit,
        ?int $count,
        string $name,
        ?int $categoryId,
        ?int $subcategoryId,
        Collection $components,
    ): void {
        $this->purchase_price = Money::fromCents($purchaseCents)->toInput();
        $this->sales_price = Money::fromCents($salesCents)->toInput();
        $this->billing_interval_unit = $unit->value;
        $this->billing_interval_count = $count ?? 1;
        $this->category_id = (string) ($categoryId ?? '');
        $this->subcategory_id = (string) ($subcategoryId ?? '');

        if ($this->name === '') {
            $this->name = $name;
        }

        $this->components = $components
            ->map(fn ($component): array => [
                'id' => null,
                'title' => $component->title,
                'description' => (string) $component->description,
                'purchase_price' => $component->purchasePrice()?->toInput() ?? '',
                'sales_price' => $component->salesPrice()?->toInput() ?? '',
            ])
            ->all();

        $this->components === [] && $this->addComponent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'billing_label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_map(
                fn (CustomerServiceStatus $status): string => $status->value,
                CustomerServiceStatus::selectable(),
            ))],
            'purchase_price' => ['required', 'string', $this->moneyRule()],
            'sales_price' => ['required', 'string', $this->moneyRule()],
            'billing_interval_unit' => ['required', Rule::in(BillingIntervalUnit::values())],
            'billing_interval_count' => ['required', 'integer', 'min:1', 'max:999'],
            'service_start_date' => ['nullable', 'date'],
            'billing_start_date' => ['nullable', 'date'],
            'first_billing_date' => ['nullable', 'date'],
            'components.*.title' => ['nullable', 'string', 'max:255'],
            'components.*.description' => ['nullable', 'string', 'max:1000'],
            'components.*.purchase_price' => ['nullable', 'string', $this->moneyRule()],
            'components.*.sales_price' => ['nullable', 'string', $this->moneyRule()],
        ];
    }

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
            'name' => 'Interner Anzeigename',
            'billing_label' => 'Rechnungsbezeichnung',
            'description' => 'Beschreibung',
            'status' => 'Status',
            'purchase_price' => 'Einkaufspreis',
            'sales_price' => 'Verkaufspreis',
            'billing_interval_unit' => 'Abrechnungsintervall',
            'billing_interval_count' => 'Intervallanzahl',
            'service_start_date' => 'Leistungsbeginn',
            'billing_start_date' => 'Abrechnungsstart',
            'first_billing_date' => 'Erstes Abrechnungsdatum',
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function variants(): Collection
    {
        if ($this->product_id === '') {
            return collect();
        }

        return ProductVariant::query()
            ->where('product_id', $this->product_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
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
     * @return Collection<int, User>
     */
    private function responsibleUsers(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
