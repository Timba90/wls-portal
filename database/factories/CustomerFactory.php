<?php

namespace Database\Factories;

use App\Actions\Customers\GenerateCustomerNumber;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = $this->faker->company();

        return [
            'customer_number' => app(GenerateCustomerNumber::class)(),
            'type' => CustomerType::Company,
            'company_name' => $company,
            'short_label' => Str::of($company)->before(' ')->limit(30, '')->toString(),
            'internal_code' => Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $company) ?: 'KD', 0, 4)),
            'status' => CustomerStatus::Active,
            'responsible_user_id' => null,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (): array => ['type' => CustomerType::Company]);
    }

    public function privatePerson(): static
    {
        return $this->state(function (): array {
            $gender = $this->faker->randomElement([Gender::Male, Gender::Female]);
            $firstName = $gender === Gender::Male
                ? $this->faker->firstNameMale()
                : $this->faker->firstNameFemale();
            $lastName = $this->faker->lastName();

            return [
                'type' => CustomerType::Private,
                'company_name' => null,
                'salutation' => $gender === Gender::Male ? Salutation::Herr : Salutation::Frau,
                'academic_title' => null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $this->faker->dateTimeBetween('-70 years', '-20 years'),
                'gender' => $gender,
                'short_label' => "{$firstName} {$lastName}",
                'internal_code' => Str::upper(Str::substr($lastName, 0, 4)),
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomerStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
