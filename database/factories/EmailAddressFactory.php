<?php

namespace Database\Factories;

use App\Enums\ContactChannelType;
use App\Models\EmailAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAddress>
 */
class EmailAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'type' => ContactChannelType::Business,
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
