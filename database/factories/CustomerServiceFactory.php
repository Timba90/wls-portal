<?php

namespace Database\Factories;

use App\Enums\BillingIntervalUnit;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Models\Customer;
use App\Models\CustomerService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerService>
 */
class CustomerServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $einkauf = $this->faker->numberBetween(0, 3000);

        return [
            'customer_id' => Customer::factory(),
            'product_id' => null,
            'product_variant_id' => null,
            'catalog_snapshot' => null,
            'name' => $this->faker->randomElement([
                'Hosting Unternehmenswebseite', 'Wartung Onlineshop', 'Nextcloud Team',
                'Backup Fileserver', 'Monitoring Webauftritt', 'Supportkontingent',
            ]),
            'billing_label' => null,
            'description' => null,
            'status' => CustomerServiceStatus::Active,
            'purchase_price_cents' => $einkauf,
            'sales_price_cents' => (int) round($einkauf * $this->faker->randomFloat(2, 1.5, 3.0)) + 1000,
            'billing_interval_unit' => BillingIntervalUnit::Month,
            'billing_interval_count' => 1,
            'service_start_date' => now()->subMonths($this->faker->numberBetween(1, 36))->startOfMonth(),
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (): array => ['status' => CustomerServiceStatus::Planned]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => ['status' => CustomerServiceStatus::Paused]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['status' => CustomerServiceStatus::Ended]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomerServiceStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (): array => [
            'billing_interval_unit' => BillingIntervalUnit::Year,
            'billing_interval_count' => 1,
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (): array => [
            'billing_interval_unit' => BillingIntervalUnit::Once,
            'billing_interval_count' => null,
        ]);
    }

    public function doNotBill(DoNotBillReason $reason = DoNotBillReason::Included): static
    {
        return $this->state(fn (): array => [
            'do_not_bill' => true,
            'do_not_bill_reason' => $reason,
            'do_not_bill_since' => now(),
        ]);
    }
}
