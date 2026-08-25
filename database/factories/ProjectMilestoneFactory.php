<?php

namespace Database\Factories;

use App\Enums\MilestoneStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement(['Konzept', 'Design', 'Umsetzung', 'Testphase', 'Livegang']),
            'note' => fake()->optional()->sentence(),
            'status' => MilestoneStatus::Open,
            'due_date' => fake()->dateTimeBetween('now', '+3 months'),
            'sort_order' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['status' => MilestoneStatus::Done]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => MilestoneStatus::InProgress]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => MilestoneStatus::Open,
            'due_date' => now()->subDays(3),
        ]);
    }
}
