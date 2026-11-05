<?php

namespace App\Livewire\CustomerPortal;

use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Verlauf eines Tickets mit Antwortfeld.
 *
 * Interne Notizen des Teams erscheinen hier nie. Ein fremdes Ticket fuehrt zu
 * 404 — auch dann, wenn die Nummer existiert.
 */
#[Layout('components.layouts.customer')]
class TicketDetail extends Component
{
    use WithPortalCustomer;

    #[Locked]
    public int $ticketId;

    public string $reply = '';

    public function mount(Ticket $ticket): void
    {
        abort_unless($ticket->customer_id === $this->customer()->id, 404);

        $this->ticketId = $ticket->id;
    }

    public function sendReply(AddTicketMessage $addTicketMessage): void
    {
        $addTicketMessage($this->ticket(), $this->reply, $this->portalUser());

        $this->reset('reply');
        $this->dispatch('antwort-gesendet');
    }

    /**
     * Der Kunde kann sein Anliegen selbst als erledigt markieren.
     */
    public function markResolved(UpdateTicket $updateTicket): void
    {
        $ticket = $this->ticket();

        if ($ticket->isOpen()) {
            $updateTicket($ticket, ['status' => TicketStatus::Resolved->value]);
        }
    }

    public function render(): View
    {
        $ticket = $this->ticket()->load([
            'project',
            'publicMessages.author',
        ]);

        return view('livewire.customer-portal.ticket-detail', [
            'ticket' => $ticket,
        ])->title($ticket->ticket_number);
    }

    private function ticket(): Ticket
    {
        return Ticket::query()
            ->whereBelongsTo($this->customer())
            ->findOrFail($this->ticketId);
    }
}
