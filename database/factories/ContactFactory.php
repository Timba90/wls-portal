<?php

namespace Database\Factories;

use App\Enums\ContactMethod;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = $this->faker->randomElement([Gender::Male, Gender::Female]);

        return [
            'salutation' => $gender === Gender::Male ? Salutation::Herr : Salutation::Frau,
            'academic_title' => null,
            'first_name' => $gender === Gender::Male
                ? $this->faker->firstNameMale()
                : $this->faker->firstNameFemale(),
            'last_name' => $this->faker->lastName(),
            'gender' => $gender,
            'birth_date' => null,
            'preferred_contact_method' => ContactMethod::Email,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
