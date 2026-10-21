<?php

namespace App\Actions\Catalog;

use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Legt einen Katalogartikel an oder aktualisiert ihn — inklusive Tags und
 * Leistungsbestandteilen.
 */
class SaveProduct
{
    public function __construct(
        private readonly SyncTags $syncTags,
        private readonly SyncServiceComponents $syncServiceComponents,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $tags
     * @param  array<int, array<string, mixed>>  $components
     */
    public function __invoke(
        array $attributes,
        array $tags = [],
        array $components = [],
        ?Product $product = null,
    ): Product {
        $licenseFields = ['purchase_cost_model', 'purchase_usage_unit', 'purchase_amount_cents', 'purchase_currency', 'purchase_eur_amount_cents', 'purchase_interval_unit', 'purchase_interval_count', 'purchase_units_per_pack'];
        $license = array_merge([
            'purchase_cost_model' => 'per_service', 'purchase_usage_unit' => 'user',
            'purchase_amount_cents' => 0, 'purchase_currency' => 'EUR', 'purchase_eur_amount_cents' => null,
            'purchase_interval_unit' => 'month', 'purchase_interval_count' => 1, 'purchase_units_per_pack' => null,
        ], $product ? array_intersect_key($product->getAttributes(), array_flip($licenseFields)) : [], array_intersect_key($attributes, array_flip($licenseFields)));
        Validator::make($license, [
            'purchase_cost_model' => ['required', 'in:per_service,per_unit,shared_unlimited,shared_pack'],
            'purchase_usage_unit' => ['required', 'in:user,customer,website,project'],
            'purchase_amount_cents' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'purchase_currency' => ['required', 'in:EUR,USD'],
            'purchase_eur_amount_cents' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'purchase_interval_unit' => ['required', 'in:month,year'],
            'purchase_interval_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'purchase_units_per_pack' => ['required_if:purchase_cost_model,shared_pack', 'nullable', 'integer', 'min:1', 'max:1000000000'],
        ])->validate();

        $candidate = $product ? clone $product : new Product;
        $candidate->fill($license);
        $summary = $candidate->licenseCostSummary();
        if ($summary['amount_cents'] > 1000000000000 || ($summary['eur_amount_cents'] ?? 0) > 1000000000000) {
            throw ValidationException::withMessages(['purchase_amount_cents' => 'Die gesamten Lizenzkosten sind zu hoch.']);
        }

        $this->guardAgainstMismatchedSubcategory(
            $attributes['category_id'] ?? null,
            $attributes['subcategory_id'] ?? null,
        );

        return DB::transaction(function () use ($attributes, $tags, $components, $product, $license): Product {
            $unit = BillingIntervalUnit::from($attributes['default_billing_interval_unit']);

            $values = [
                'name' => $attributes['name'],
                'internal_name' => $attributes['internal_name'],
                'description' => $attributes['description'] ?? null,
                'category_id' => $attributes['category_id'] ?? null,
                'subcategory_id' => $attributes['subcategory_id'] ?? null,
                'status' => $attributes['status'] ?? CatalogStatus::Active,
                'default_purchase_price_cents' => Money::fromEuroInput($attributes['default_purchase_price'] ?? null)->cents,
                'default_sales_price_cents' => Money::fromEuroInput($attributes['default_sales_price'] ?? null)->cents,
                'default_billing_interval_unit' => $unit,
                'default_billing_interval_count' => $unit->requiresCount()
                    ? max(1, (int) ($attributes['default_billing_interval_count'] ?? 1))
                    : null,
            ];

            $values = array_merge($values, $license);

            if ($product) {
                $product->update($values);
            } else {
                $product = Product::query()->create($values);
            }

            ($this->syncTags)($product, $tags);
            ($this->syncServiceComponents)($product, $components);

            return $product;
        });
    }

    /**
     * Die Unterkategorie muss der gewaehlten Kategorie untergeordnet sein.
     */
    private function guardAgainstMismatchedSubcategory(?int $categoryId, ?int $subcategoryId): void
    {
        if (is_null($subcategoryId)) {
            return;
        }

        if (is_null($categoryId)) {
            throw ValidationException::withMessages([
                'subcategory_id' => 'Eine Unterkategorie setzt eine Kategorie voraus.',
            ]);
        }

        $subcategory = Category::query()->findOrFail($subcategoryId);

        if ($subcategory->parent_id !== $categoryId) {
            throw ValidationException::withMessages([
                'subcategory_id' => 'Die Unterkategorie gehört nicht zur gewählten Kategorie.',
            ]);
        }
    }
}
