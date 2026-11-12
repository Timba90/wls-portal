<?php

namespace Database\Factories;

use App\Models\LexofficeRecurringTemplate;
use App\Models\LexofficeRecurringTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LexofficeRecurringTemplateItem>
 */
class LexofficeRecurringTemplateItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->numberBetween(500, 15000);

        return [
            'lexoffice_recurring_template_id' => LexofficeRecurringTemplate::factory(),
            'lexoffice_line_item_id' => (string) Str::uuid(),
            'position' => 0,
            'type' => 'custom',
            'name' => $this->faker->randomElement(['Webspace Advanced Paket', 'Wartung Webseite', 'E-Mail-Postfach']),
            'description' => null,
            'quantity' => 1,
            'unit_name' => 'Monate',
            'unit_price_net_cents' => $price,
            'discount_percentage' => 0,
            'tax_rate_percentage' => 19,
            'line_net_cents' => $price,
            'customer_service_id' => null,
            'match_source' => null,
            'is_ignored' => false,
        ];
    }

    public function ignored(): static
    {
        return $this->state(fn (): array => ['is_ignored' => true]);
    }
}
