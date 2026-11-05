<?php

namespace App\Notifications\Tickets;

use App\Models\PortalUser;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * E-Mail zu einem neuen Ticket oder einer neuen Antwort.
 *
 * Kunden landen im Kundenportal, das Team in der internen Ticketansicht.
 */
class TicketActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public ?TicketMessage $message,
        public bool $opened = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(PortalUser|User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(PortalUser|User $notifiable): MailMessage
    {
        $kunde = $notifiable instanceof PortalUser;
        $nummer = $this->ticket->ticket_number;

        $mail = (new MailMessage)
            ->subject(($this->opened ? 'Neues Ticket ' : 'Neue Antwort zu ').$nummer.': '.$this->ticket->subject)
            ->greeting('Hallo '.$notifiable->name.',');

        $mail->line(match (true) {
            $kunde && $this->opened => 'zu Ihrem Anliegen wurde ein Ticket angelegt.',
            $kunde => 'es gibt eine neue Antwort zu Ihrem Ticket.',
            $this->opened => 'für '.$this->ticket->customer->displayName().' wurde ein neues Ticket eröffnet.',
            default => $this->ticket->customer->displayName().' hat auf ein Ticket geantwortet.',
        });

        if ($this->message !== null) {
            $mail->line('„'.Str::limit($this->message->body, 400).'"');
        }

        return $mail->action(
            'Ticket öffnen',
            $kunde
                ? route('customer-portal.tickets.show', $this->ticket)
                : route('tickets.show', $this->ticket),
        );
    }
}
