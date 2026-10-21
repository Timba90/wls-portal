<?php

namespace Database\Factories;

use App\Enums\PleskServer;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return ['server' => PleskServer::Cloud01, 'name' => fake()->unique()->domainName(), 'status' => SubscriptionStatus::Active];
    }
}
