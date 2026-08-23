<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Bestandskunde', 'Neukunde', 'Wartungsvertrag', 'Managed', 'Selbstverwaltet',
                'Kritisch', 'Saisonal', 'Kündigung geplant', 'Ausbaupotenzial', 'Referenzkunde',
            ]),
            'color' => $this->faker->randomElement(['gray', 'blue', 'green', 'amber', 'red', 'purple']),
        ];
    }
}
