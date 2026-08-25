<?php

namespace Database\Factories;

use App\Actions\Projects\GenerateProjectNumber;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Die Deadline liegt bewusst immer in der Zukunft. Eine Factory, die
        // zufaellig ueberfaellige Projekte erzeugt, macht jeden Test
        // unzuverlaessig, der nur „irgendein Projekt" braucht; wer ein
        // ueberfaelliges will, nimmt den Zustand `overdue()`.
        $beginn = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'project_number' => app(GenerateProjectNumber::class)(),
            'name' => fake()->randomElement(['Relaunch', 'Onlineshop', 'Kundenportal', 'Schnittstelle', 'Intranet'])
                .' '.fake()->company(),
            'description' => fake()->optional()->sentence(),
            'customer_id' => Customer::factory(),
            'project_type_id' => ProjectType::factory(),
            'responsible_user_id' => null,
            'status' => ProjectStatus::Active,
            'start_date' => $beginn,
            'deadline' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'risk_note' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::Planned]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::Completed]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectStatus::Active,
            'deadline' => now()->subDays(5),
        ]);
    }
}
