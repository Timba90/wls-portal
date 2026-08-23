<?php

namespace App\Actions\Catalog;

use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Legt eine Artikelvariante an oder aktualisiert sie.
 *
 * Leere Preis- und Intervallangaben bedeuten: der Wert des Katalogartikels
 * gilt weiter.
 */
class SaveProductVariant
{
    public function __construct(private readonly SyncServiceComponents $syncServiceComponents) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $components
     */
    public function __invoke(
        Product $product,
        array $attributes,
        array $components = [],
        ?ProductVariant $variant = null,
    ): ProductVariant {
        return DB::transaction(function () use ($product, $attributes, $components, $variant): ProductVariant {
            $unit = filled($attributes['billing_interval_unit'] ?? null)
                ? BillingIntervalUnit::from($attributes['billing_interval_unit'])
                : null;

            $values = [
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'purchase_price_cents' => $this->toCents($attributes['purchase_price'] ?? null),
                'sales_price_cents' => $this->toCents($attributes['sales_price'] ?? null),
                'billing_interval_unit' => $unit,
                'billing_interval_count' => $unit?->requiresCount()
                    ? max(1, (int) ($attributes['billing_interval_count'] ?? 1))
                    : null,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'status' => $attributes['status'] ?? CatalogStatus::Active,
            ];

            if ($variant) {
                $variant->update($values);
            } else {
                $variant = $product->variants()->create($values);
            }

            ($this->syncServiceComponents)($variant, $components);

            return $variant;
        });
    }

    private function toCents(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::fromEuroInput($value)->cents;
    }
}
