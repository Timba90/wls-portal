<?php

namespace Database\Factories;

use App\Enums\CatalogStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->randomElement(['Basic', 'Business', 'Premium']),
            'description' => null,
            'purchase_price_cents' => null,
            'sales_price_cents' => null,
            'billing_interval_unit' => null,
            'billing_interval_count' => null,
            'sort_order' => 0,
            'status' => CatalogStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => CatalogStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
