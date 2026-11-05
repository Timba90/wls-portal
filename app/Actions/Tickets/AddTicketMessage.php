<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\PortalUser;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Haengt eine Nachricht an ein Ticket und fuehrt den Status mit.
 *
 * - Antwortet der Kunde, liegt der Ball wieder beim Team: das Ticket wird
 *   (wieder) offen, auch wenn es schon geloest oder geschlossen war.
 * - Antwortet das Team oeffentlich auf ein offenes Ticket, wartet es danach
 *   auf den Kunden — ausser es ist ausdruecklich in Bearbeitung.
 * - Interne Notizen aendern den Status nicht und erreichen den Kunden nie.
 */
class AddTicketMessage
{
    public function __construct(private readonly NotifyAboutTicketActivity $notify) {}

    public function __invoke(
        Ticket $ticket,
        mixed $body,
        PortalUser|User|null $author = null,
        bool $isInternal = false,
    ): TicketMessage {
        $validated = Validator::make(['body' => $body], [
            'body' => ['required', 'string', 'max:20000'],
        ], attributes: ['body' => 'Nachricht'])->validate();

        if ($isInternal && ! $author instanceof User) {
            throw ValidationException::withMessages([
                'body' => 'Interne Notizen können nur vom Team geschrieben werden.',
            ]);
        }

        $message = DB::transaction(function () use ($ticket, $validated, $author, $isInternal): TicketMessage {
            $message = $ticket->messages()->create([
                'author_type' => $author?->getMorphClass(),
                'author_id' => $author?->getKey(),
                'body' => trim($validated['body']),
                'is_internal' => $isInternal,
            ]);

            $ticket->forceFill(['last_activity_at' => now()]);

            if (! $isInternal) {
                $this->advanceStatus($ticket, $author);
            }

            $ticket->save();

            return $message;
        });

        if (! $isInternal) {
            ($this->notify)($ticket, $message);
        }

        return $message;
    }

    private function advanceStatus(Ticket $ticket, PortalUser|User|null $author): void
    {
        if ($author instanceof User) {
            if ($ticket->status === TicketStatus::Open) {
                $ticket->status = TicketStatus::WaitingForCustomer;
            }

            return;
        }

        // Kunde oder automatische Meldung: das Team ist am Zug.
        if ($ticket->status !== TicketStatus::InProgress) {
            $ticket->status = TicketStatus::Open;
            $ticket->closed_at = null;
        }
    }
}
