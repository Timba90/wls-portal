<?php

namespace App\Livewire\CustomerPortal;

use App\Enums\TicketStatus;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alle Tickets des Kunden — auch die, die ein Kollege oder ein System
 * eroeffnet hat. Ein Ticket gehoert dem Kunden, nicht dem einzelnen Zugang.
 */
#[Layout('components.layouts.customer')]
class TicketList extends Component
{
    use WithPagination, WithPortalCustomer;

    #[Url(as: 'ansicht', except: 'offen')]
    public string $view = 'offen';

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $customer = $this->customer();

        $tickets = Ticket::query()
            ->whereBelongsTo($customer)
            ->with('project')
            ->withCount('publicMessages')
            ->when($this->view === 'offen', fn ($query) => $query->open())
            ->when($this->view === 'erledigt', fn ($query) => $query->whereNotIn('status', TicketStatus::openStates()))
            ->when(filled($this->search), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('subject', 'like', '%'.trim($this->search).'%')
                ->orWhere('ticket_number', 'like', '%'.trim($this->search).'%')))
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(20);

        return view('livewire.customer-portal.ticket-list', [
            'tickets' => $tickets,
            'openCount' => Ticket::query()->whereBelongsTo($customer)->open()->count(),
            'waitingCount' => Ticket::query()->whereBelongsTo($customer)->where('status', TicketStatus::WaitingForCustomer)->count(),
            'totalCount' => Ticket::query()->whereBelongsTo($customer)->count(),
        ])->title('Tickets');
    }
}
