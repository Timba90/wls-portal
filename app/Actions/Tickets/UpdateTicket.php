<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Bearbeitung durch das Team: Status, Prioritaet, Zustaendigkeit und Projekt.
 *
 * Geaendert werden nur die mitgegebenen Felder, damit die Schnittstelle
 * einzelne Werte setzen kann, ohne die uebrigen zu kennen.
 */
class UpdateTicket
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(Ticket $ticket, array $input): Ticket
    {
        $validated = Validator::make($input, [
            'subject' => ['sometimes', 'required', 'string', 'max:191'],
            'status' => ['sometimes', 'required', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TicketPriority::class)],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')->where('customer_id', $ticket->customer_id)],
        ], attributes: [
            'subject' => 'Betreff',
            'status' => 'Status',
            'priority' => 'Priorität',
            'assigned_user_id' => 'Zuständig',
            'project_id' => 'Projekt',
        ])->validate();

        $ticket->fill($validated);

        if ($ticket->isDirty('status')) {
            $ticket->closed_at = $ticket->status === TicketStatus::Closed ? now() : null;
            $ticket->last_activity_at = now();
        }

        $ticket->save();

        return $ticket;
    }
}
