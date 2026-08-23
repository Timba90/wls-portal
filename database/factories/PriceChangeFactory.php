<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Models\CustomerService;
use App\Models\PriceChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceChange>
 */
class PriceChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_service_id' => CustomerService::factory(),
            'price_type' => PriceType::Sales,
            'old_price_cents' => 4900,
            'new_price_cents' => 5900,
            'effective_date' => now()->addMonth()->toDateString(),
            'applied_at' => null,
            'user_id' => null,
            'note' => null,
        ];
    }

    public function applied(): static
    {
        return $this->state(fn (): array => [
            'effective_date' => now()->subMonth()->toDateString(),
            'applied_at' => now()->subMonth(),
        ]);
    }

    public function purchase(): static
    {
        return $this->state(fn (): array => ['price_type' => PriceType::Purchase]);
    }
}
