<?php

namespace Database\Factories;

use App\Models\ContactRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRole>
 */
class ContactRoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Geschäftsführung', 'Technik', 'Buchhaltung', 'Einkauf', 'Marketing',
                'Vertrieb', 'Datenschutz', 'IT-Leitung', 'Personal', 'Empfang',
            ]),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
