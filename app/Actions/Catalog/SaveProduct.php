<?php

namespace App\Actions\Catalog;

use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
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
        $this->guardAgainstMismatchedSubcategory(
            $attributes['category_id'] ?? null,
            $attributes['subcategory_id'] ?? null,
        );

        return DB::transaction(function () use ($attributes, $tags, $components, $product): Product {
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
