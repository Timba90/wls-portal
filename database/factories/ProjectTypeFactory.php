<?php

namespace Database\Factories;

use App\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectType>
 */
class ProjectTypeFactory extends Factory
{
    protected $model = ProjectType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Eindeutig macht die Nummer. `unique()` auf der Liste selbst deckelte
        // die Factory bei fuenf Typen je Test — der sechste Aufruf brach ab.
        $name = fake()->randomElement(['Webseite', 'Shop', 'Web-App', 'API', 'Internes Tool'])
            .' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'short_label' => mb_strtoupper(mb_substr($name, 0, 2)),
            'color' => fake()->randomElement(['green', 'blue', 'purple', 'amber', 'gray']),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
