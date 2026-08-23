<?php

namespace Database\Factories;

use App\Enums\BillingIntervalUnit;
use App\Enums\CatalogStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Webhosting', 'Managed Hosting', 'Webseitenwartung', 'Nextcloud',
            'Backup', 'SSL-Zertifikat', 'Monitoring', 'Support', 'Webentwicklung',
        ]);

        $einkauf = $this->faker->numberBetween(500, 4000);

        return [
            'name' => $name,
            'internal_name' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(100, 999),
            'description' => null,
            'category_id' => null,
            'subcategory_id' => null,
            'status' => CatalogStatus::Active,
            'default_purchase_price_cents' => $einkauf,
            'default_sales_price_cents' => (int) round($einkauf * $this->faker->randomFloat(2, 1.4, 2.6)),
            'default_billing_interval_unit' => BillingIntervalUnit::Month,
            'default_billing_interval_count' => 1,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => CatalogStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (): array => [
            'default_billing_interval_unit' => BillingIntervalUnit::Once,
            'default_billing_interval_count' => null,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (): array => [
            'default_billing_interval_unit' => BillingIntervalUnit::Year,
            'default_billing_interval_count' => 1,
        ]);
    }
}
