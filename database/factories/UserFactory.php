<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('GeheimesPasswort1!'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Benutzer mit bestätigter Zwei-Faktor-Authentifizierung.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt('GEHEIMERSCHLUESSEL'),
            'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-bbbb', 'cccc-dddd'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
