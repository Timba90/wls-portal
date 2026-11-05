<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    protected $model = TicketMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'author_type' => null,
            'author_id' => null,
            'body' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }

    public function fromTeam(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'author_type' => (new User)->getMorphClass(),
            'author_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (): array => ['is_internal' => true]);
    }
}
