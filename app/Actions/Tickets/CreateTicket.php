<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\PortalUser;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Eroeffnet ein Ticket samt erster Nachricht.
 *
 * Gemeinsamer Weg fuer Kundenportal, interne Erfassung und Schnittstelle.
 * Die Validierung sitzt deshalb hier und nicht in der Oberflaeche.
 *
 * Automatisch erzeugte Tickets duerfen eine `external_reference` mitbringen —
 * etwa die Kennung eines Ueberwachungsalarms. Meldet dasselbe System dieselbe
 * Referenz erneut, entsteht kein zweites Ticket: die Meldung wird als
 * Nachricht angehaengt und ein bereits geschlossenes Ticket wieder geoeffnet.
 */
class CreateTicket
{
    public function __construct(
        private readonly GenerateTicketNumber $generateTicketNumber,
        private readonly AddTicketMessage $addTicketMessage,
        private readonly NotifyAboutTicketActivity $notify,
    ) {}

    /**
     * @param  array{subject?: mixed, body?: mixed, priority?: mixed, project_id?: mixed, external_reference?: mixed}  $input
     */
    public function __invoke(
        Customer $customer,
        array $input,
        TicketSource $source,
        PortalUser|User|null $author = null,
    ): Ticket {
        $validated = Validator::make($input, [
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('customer_id', $customer->id)],
            'external_reference' => ['nullable', 'string', 'max:191'],
        ], attributes: [
            'subject' => 'Betreff',
            'body' => 'Nachricht',
            'priority' => 'Priorität',
            'project_id' => 'Projekt',
            'external_reference' => 'Externe Referenz',
        ])->validate();

        if (filled($validated['external_reference'] ?? null)) {
            $vorhanden = Ticket::query()
                ->where('source', $source)
                ->where('external_reference', $validated['external_reference'])
                ->first();

            if ($vorhanden !== null) {
                abort_unless($vorhanden->customer_id === $customer->id, 422, 'Die externe Referenz gehört zu einem anderen Kunden.');

                ($this->addTicketMessage)($vorhanden, $validated['body'], $author);

                return $vorhanden->refresh();
            }
        }

        $ticket = DB::transaction(function () use ($customer, $validated, $source, $author): Ticket {
            $ticket = Ticket::query()->create([
                'ticket_number' => ($this->generateTicketNumber)(),
                'customer_id' => $customer->id,
                'project_id' => $validated['project_id'] ?? null,
                'portal_user_id' => $author instanceof PortalUser ? $author->id : null,
                'subject' => trim($validated['subject']),
                'status' => TicketStatus::Open,
                'priority' => $validated['priority'] ?? TicketPriority::Normal->value,
                'source' => $source,
                'external_reference' => $validated['external_reference'] ?? null,
                'last_activity_at' => now(),
            ]);

            $ticket->messages()->create([
                'author_type' => $author?->getMorphClass(),
                'author_id' => $author?->getKey(),
                'body' => trim($validated['body']),
                'is_internal' => false,
            ]);

            return $ticket;
        });

        ($this->notify)($ticket, $ticket->messages()->first(), opened: true);

        return $ticket;
    }
}
