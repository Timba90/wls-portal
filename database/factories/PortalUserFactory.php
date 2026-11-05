<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PortalUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortalUser>
 */
class PortalUserFactory extends Factory
{
    protected $model = PortalUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'KundenPasswort1!',
            'is_active' => true,
            'invited_at' => now()->subWeek(),
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Eingeladen, aber noch ohne eigenes Passwort.
     */
    public function invited(): static
    {
        return $this->state(fn (): array => ['password' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
