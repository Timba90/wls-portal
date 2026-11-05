<?php

namespace Database\Factories;

use App\Actions\Tickets\GenerateTicketNumber;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => app(GenerateTicketNumber::class)(),
            'customer_id' => Customer::factory(),
            'subject' => fake()->randomElement([
                'Kontaktformular versendet keine E-Mails',
                'Bitte neues Postfach anlegen',
                'Fehlermeldung im Checkout',
                'Zertifikat läuft bald ab',
                'Frage zur letzten Rechnung',
            ]),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
            'source' => TicketSource::Portal,
            'last_activity_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function fromApi(): static
    {
        return $this->state(fn (): array => [
            'source' => TicketSource::Api,
            'external_reference' => fake()->unique()->uuid(),
        ]);
    }
}
