<?php

namespace Database\Factories;

use App\Enums\ContactChannelType;
use App\Models\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhoneNumber>
 */
class PhoneNumberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => '+49 '.$this->faker->numberBetween(30, 899).' '.$this->faker->numerify('#######'),
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
