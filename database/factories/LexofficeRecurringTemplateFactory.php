<?php

namespace Database\Factories;

use App\Enums\LexofficeExecutionInterval;
use App\Models\LexofficeRecurringTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LexofficeRecurringTemplate>
 */
class LexofficeRecurringTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lexoffice_id' => (string) Str::uuid(),
            'lexoffice_contact_id' => (string) Str::uuid(),
            'contact_name' => $this->faker->company(),
            'customer_id' => null,
            'title' => 'Rechnung',
            'execution_interval' => LexofficeExecutionInterval::Monthly,
            'execution_status' => LexofficeRecurringTemplate::StatusActive,
            'start_date' => now()->subYear()->startOfMonth(),
            'next_execution_date' => now()->endOfMonth(),
            'last_execution_date' => now()->subMonth()->endOfMonth(),
            'last_execution_failed' => false,
            'tax_type' => 'net',
            'total_net_cents' => 0,
            'total_gross_cents' => 0,
            'lexoffice_updated_at' => now(),
            'synced_at' => now(),
        ];
    }

    public function annually(): static
    {
        return $this->state(fn (): array => ['execution_interval' => LexofficeExecutionInterval::Annually]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['execution_status' => LexofficeRecurringTemplate::StatusEnded]);
    }
}
