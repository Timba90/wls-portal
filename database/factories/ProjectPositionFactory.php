<?php

namespace Database\Factories;

use App\Enums\CustomerServiceStatus;
use App\Enums\ProjectPositionKind;
use App\Models\Project;
use App\Models\ProjectPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPosition>
 */
class ProjectPositionFactory extends Factory
{
    protected $model = ProjectPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'product_id' => null,
            'customer_service_id' => null,
            'name' => fake()->randomElement(['Konzeption', 'Screendesign', 'Umsetzung', 'Schulung', 'Betreuung']),
            'kind' => ProjectPositionKind::OneTime,
            'quantity' => 1,
            'unit_price_cents' => fake()->numberBetween(5000, 500000),
            'status' => CustomerServiceStatus::Planned,
            'sort_order' => 0,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn (): array => ['kind' => ProjectPositionKind::Recurring]);
    }
}
