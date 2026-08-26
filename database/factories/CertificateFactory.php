<?php

namespace Database\Factories;

use App\Enums\RegistrarProvider;
use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'common_name' => fake()->unique()->domainWord().'-'.fake()->unique()->numberBetween(1, 99999).'.de',
            'provider' => RegistrarProvider::Inwx,
            'provider_reference' => (string) fake()->unique()->numberBetween(100000, 999999),
            'status' => 'issued',
            'issuer' => 'Sectigo',
            'issued_on' => now()->subMonths(3)->startOfDay(),
            'expires_on' => now()->addMonths(fake()->numberBetween(2, 9))->startOfDay(),
            'alternative_names' => [],
            'synced_at' => now(),
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (): array => ['expires_on' => now()->addDays(10)->startOfDay()]);
    }
}
