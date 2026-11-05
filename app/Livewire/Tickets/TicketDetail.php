<?php

namespace App\Livewire\Tickets;

use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ticket aus Sicht des Teams: Verlauf inklusive interner Notizen, Antwort an
 * den Kunden und Bearbeitung von Status, Prioritaet und Zustaendigkeit.
 */
#[Layout('components.layouts.app')]
class TicketDetail extends Component
{
    public Ticket $ticket;

    public string $reply = '';

    public bool $internal = false;

    public string $status = '';

    public string $priority = '';

    public string $assignedUserId = '';

    public string $projectId = '';

    public function mount(Ticket $ticket): void
    {
        $this->ticket = $ticket;
        $this->fillFromTicket();
    }

    public function sendReply(AddTicketMessage $addTicketMessage): void
    {
        /** @var User $user */
        $user = auth()->user();

        $addTicketMessage($this->ticket, $this->reply, $user, $this->internal);

        $this->reset('reply', 'internal');
        $this->ticket->refresh();
        $this->fillFromTicket();
        $this->dispatch('nachricht-gespeichert');
    }

    public function saveDetails(UpdateTicket $updateTicket): void
    {
        $updateTicket($this->ticket, [
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_user_id' => blank($this->assignedUserId) ? null : (int) $this->assignedUserId,
            'project_id' => blank($this->projectId) ? null : (int) $this->projectId,
        ]);

        $this->ticket->refresh();
        $this->dispatch('ticket-gespeichert');
    }

    public function assignToMe(UpdateTicket $updateTicket): void
    {
        $updateTicket($this->ticket, [
            'assigned_user_id' => auth()->id(),
            ...($this->ticket->status === TicketStatus::Open ? ['status' => TicketStatus::InProgress->value] : []),
        ]);

        $this->ticket->refresh();
        $this->fillFromTicket();
    }

    public function render(): View
    {
        $this->ticket->load(['customer', 'project', 'portalUser', 'assignedUser', 'messages.author']);

        return view('livewire.tickets.ticket-detail', [
            'statusOptions' => TicketStatus::options(),
            'priorityOptions' => TicketPriority::options(),
            'users' => User::query()->orderBy('name')->get()->map(fn (User $user): array => ['label' => $user->name, 'value' => (string) $user->id])->all(),
            'projects' => Project::query()
                ->whereBelongsTo($this->ticket->customer)
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project): array => ['label' => $project->project_number.' · '.$project->name, 'value' => (string) $project->id])
                ->all(),
        ])->title($this->ticket->ticket_number);
    }

    private function fillFromTicket(): void
    {
        $this->status = $this->ticket->status->value;
        $this->priority = $this->ticket->priority->value;
        $this->assignedUserId = (string) ($this->ticket->assigned_user_id ?? '');
        $this->projectId = (string) ($this->ticket->project_id ?? '');
    }
}
