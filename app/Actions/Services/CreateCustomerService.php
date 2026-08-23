<?php

namespace App\Actions\Services;

use App\Actions\Catalog\SyncServiceComponents;
use App\Actions\Catalog\SyncTags;
use App\Enums\BillingIntervalUnit;
use App\Enums\CustomerServiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Legt eine Kundenleistung an — mit oder ohne Katalogartikel.
 *
 * Ohne Katalogartikel entsteht eine vollstaendig individuelle Leistung.
 */
class CreateCustomerService
{
    public function __construct(
        private readonly BuildCatalogSnapshot $buildCatalogSnapshot,
        private readonly SyncTags $syncTags,
        private readonly SyncServiceComponents $syncServiceComponents,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $tags
     * @param  array<int, array<string, mixed>>  $components
     */
    public function __invoke(
        Customer $customer,
        array $attributes,
        array $tags = [],
        array $components = [],
    ): CustomerService {
        return DB::transaction(function () use ($customer, $attributes, $tags, $components): CustomerService {
            $product = filled($attributes['product_id'] ?? null)
                ? Product::query()->findOrFail($attributes['product_id'])
                : null;

            $variant = filled($attributes['product_variant_id'] ?? null)
                ? ProductVariant::query()->findOrFail($attributes['product_variant_id'])
                : null;

            $unit = BillingIntervalUnit::from($attributes['billing_interval_unit']);

            $service = $customer->services()->create([
                'product_id' => $product?->getKey(),
                'product_variant_id' => $variant?->getKey(),
                'catalog_snapshot' => ($this->buildCatalogSnapshot)($product, $variant),
                'name' => $attributes['name'],
                'billing_label' => $attributes['billing_label'] ?? null,
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'] ?? CustomerServiceStatus::Planned,
                'purchase_price_cents' => Money::fromEuroInput($attributes['purchase_price'] ?? null)->cents,
                'sales_price_cents' => Money::fromEuroInput($attributes['sales_price'] ?? null)->cents,
                'billing_interval_unit' => $unit,
                'billing_interval_count' => $unit->requiresCount()
                    ? max(1, (int) ($attributes['billing_interval_count'] ?? 1))
                    : null,
                'service_start_date' => $attributes['service_start_date'] ?? null,
                'billing_start_date' => $attributes['billing_start_date'] ?? null,
                'first_billing_date' => $attributes['first_billing_date'] ?? null,
                'category_id' => $attributes['category_id'] ?? null,
                'subcategory_id' => $attributes['subcategory_id'] ?? null,
                'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
            ]);

            ($this->syncTags)($service, $tags);
            ($this->syncServiceComponents)($service, $components);

            return $service;
        });
    }
}
