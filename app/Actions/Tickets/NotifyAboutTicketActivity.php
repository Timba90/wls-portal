<?php

namespace App\Actions\Tickets;

use App\Models\PortalUser;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\Tickets\TicketActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Wer bei neuer Aktivitaet an einem Ticket eine E-Mail bekommt.
 *
 * - Schreibt das Team, erfahren es die aktiven Kundenzugaenge: der Eroeffner
 *   des Tickets, ohne einen solchen alle Zugaenge des Kunden.
 * - Schreibt der Kunde oder ein System, erfaehrt es das Team: die zustaendige
 *   Person am Ticket, sonst die fuer den Kunden verantwortliche.
 *
 * Der Absender selbst bekommt nie eine Mail ueber seine eigene Nachricht.
 */
class NotifyAboutTicketActivity
{
    public function __invoke(Ticket $ticket, ?TicketMessage $message, bool $opened = false): void
    {
        $ticket->loadMissing(['portalUser', 'assignedUser', 'customer.responsibleUser', 'customer.portalUsers']);

        $empfaenger = $message?->isFromTeam()
            ? $this->customerRecipients($ticket)
            : $this->teamRecipients($ticket);

        $empfaenger = $empfaenger->reject(
            fn (PortalUser|User $person): bool => $message !== null
                && $message->author_type === $person->getMorphClass()
                && (int) $message->author_id === (int) $person->getKey(),
        );

        if ($empfaenger->isNotEmpty()) {
            Notification::send($empfaenger, new TicketActivity($ticket, $message, $opened));
        }
    }

    /**
     * @return Collection<int, PortalUser>
     */
    private function customerRecipients(Ticket $ticket): Collection
    {
        if ($ticket->portalUser?->is_active) {
            return collect([$ticket->portalUser]);
        }

        return $ticket->customer->portalUsers->where('is_active', true)->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function teamRecipients(Ticket $ticket): Collection
    {
        return collect([$ticket->assignedUser ?? $ticket->customer->responsibleUser])->filter()->values();
    }
}
