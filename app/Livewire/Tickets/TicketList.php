<?php

namespace App\Livewire\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alle Tickets aller Kunden — die Arbeitsliste des Teams.
 *
 * Voreingestellt sind die Tickets, bei denen das Team am Zug ist: offen und
 * in Bearbeitung. Dringende stehen oben.
 */
#[Layout('components.layouts.app')]
class TicketList extends Component
{
    use WithPagination;

    #[Url(as: 'ansicht', except: 'am-zug')]
    public string $view = 'am-zug';

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'kunde', except: '')]
    public string $customerId = '';

    #[Url(as: 'zustaendig', except: '')]
    public string $assignedUserId = '';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $tickets = Ticket::query()
            ->with(['customer', 'assignedUser', 'project'])
            ->when($this->view === 'am-zug', fn (Builder $query) => $query->whereIn('status', [TicketStatus::Open, TicketStatus::InProgress]))
            ->when($this->view === 'offen', fn (Builder $query) => $query->open())
            ->when($this->view === 'wartet', fn (Builder $query) => $query->where('status', TicketStatus::WaitingForCustomer))
            ->when($this->view === 'erledigt', fn (Builder $query) => $query->whereNotIn('status', TicketStatus::openStates()))
            ->when(filled($this->customerId), fn (Builder $query) => $query->where('customer_id', (int) $this->customerId))
            ->when($this->assignedUserId === 'ohne', fn (Builder $query) => $query->whereNull('assigned_user_id'))
            ->when(is_numeric($this->assignedUserId), fn (Builder $query) => $query->where('assigned_user_id', (int) $this->assignedUserId))
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('subject', 'like', '%'.trim($this->search).'%')
                ->orWhere('ticket_number', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('customer', fn (Builder $kunde) => $kunde
                    ->where('company_name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('last_name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('customer_number', 'like', '%'.trim($this->search).'%'))))
            ->orderByRaw($this->priorityOrder())
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(25);

        return view('livewire.tickets.ticket-list', [
            'tickets' => $tickets,
            'counts' => [
                'open' => Ticket::query()->where('status', TicketStatus::Open)->count(),
                'inProgress' => Ticket::query()->where('status', TicketStatus::InProgress)->count(),
                'waiting' => Ticket::query()->where('status', TicketStatus::WaitingForCustomer)->count(),
                'unassigned' => Ticket::query()->open()->whereNull('assigned_user_id')->count(),
            ],
            'customers' => Customer::query()
                ->whereHas('tickets')
                ->get()
                ->map(fn (Customer $customer): array => ['label' => $customer->displayName(), 'value' => (string) $customer->id])
                ->sortBy('label')
                ->values()
                ->all(),
            'users' => [
                ['label' => 'Nicht zugewiesen', 'value' => 'ohne'],
                ...User::query()->orderBy('name')->get()->map(fn (User $user): array => ['label' => $user->name, 'value' => (string) $user->id])->all(),
            ],
        ])->title('Tickets');
    }

    /**
     * Dringende zuerst — als CASE, weil die Prioritaet als Zeichenkette liegt.
     */
    private function priorityOrder(): string
    {
        $faelle = collect(TicketPriority::cases())
            ->map(fn (TicketPriority $prioritaet): string => "when '{$prioritaet->value}' then {$prioritaet->weight()}")
            ->implode(' ');

        return "case priority {$faelle} else 0 end desc";
    }
}
