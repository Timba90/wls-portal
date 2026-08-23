<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => $this->faker->unique()->randomElement([
                'Hosting', 'Webentwicklung', 'Cloud', 'Domains', 'Support',
                'Sicherheit', 'Monitoring', 'Beratung', 'Schulung', 'Lizenzen',
            ]),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}
