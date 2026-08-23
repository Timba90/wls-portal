<?php

namespace App\Actions\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Haelt die Katalogwerte zum Zeitpunkt der Verknuepfung fest.
 *
 * Damit laesst sich spaeter unterscheiden, ob eine Kundenleistung bewusst vom
 * Katalog abweicht oder ob sich der Katalog seither geaendert hat. Bestehende
 * Kundenleistungen werden bei Katalogaenderungen niemals automatisch angepasst.
 */
class BuildCatalogSnapshot
{
    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(?Product $product, ?ProductVariant $variant = null): ?array
    {
        if (! $product) {
            return null;
        }

        $interval = $variant?->effectiveBillingInterval() ?? $product->defaultBillingInterval();

        return [
            'erfasst_am' => now()->toIso8601String(),
            'product_id' => $product->getKey(),
            'product_name' => $product->name,
            'product_variant_id' => $variant?->getKey(),
            'product_variant_name' => $variant?->name,
            'purchase_price_cents' => $variant?->effectivePurchasePrice()->cents
                ?? $product->default_purchase_price_cents,
            'sales_price_cents' => $variant?->effectiveSalesPrice()->cents
                ?? $product->default_sales_price_cents,
            'billing_interval_unit' => $interval->unit->value,
            'billing_interval_count' => $interval->count,
            'category_id' => $product->category_id,
            'subcategory_id' => $product->subcategory_id,
        ];
    }
}
