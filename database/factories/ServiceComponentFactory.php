<?php

namespace Database\Factories;

use App\Models\ServiceComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceComponent>
 */
class ServiceComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->randomElement([
                'Hosting', 'Tägliches Backup', 'Monitoring', 'Updates',
                '30 Minuten Support', 'SSL-Zertifikat', 'Statistiken',
            ]),
            'description' => null,
            'sort_order' => 0,
            'purchase_price_cents' => null,
            'sales_price_cents' => null,
        ];
    }
}
