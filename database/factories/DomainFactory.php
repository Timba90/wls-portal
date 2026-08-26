<?php

namespace Database\Factories;

use App\Enums\RegistrarProvider;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->domainWord().'-'.fake()->unique()->numberBetween(1, 99999).'.de';

        return [
            'name' => $name,
            'provider' => RegistrarProvider::AutoDns,
            'provider_reference' => (string) fake()->unique()->numberBetween(100000, 999999),
            'status' => 'ok',
            'registered_on' => now()->subYears(2)->startOfDay(),
            // In der Zukunft, damit nichts zufaellig abgelaufen ist.
            'expires_on' => now()->addMonths(fake()->numberBetween(2, 11))->startOfDay(),
            'auto_renew' => true,
            'nameservers' => ['ns1.example.net', 'ns2.example.net'],
            'synced_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_on' => now()->subDays(7)->startOfDay()]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (): array => ['expires_on' => now()->addDays(10)->startOfDay()]);
    }
}
