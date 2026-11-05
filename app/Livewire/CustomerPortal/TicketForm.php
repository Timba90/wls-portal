<?php

namespace App\Livewire\CustomerPortal;

use App\Actions\Tickets\CreateTicket;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Neues Anliegen melden.
 */
#[Layout('components.layouts.customer')]
class TicketForm extends Component
{
    use WithPortalCustomer;

    public string $subject = '';

    public string $body = '';

    public string $priority = 'normal';

    #[Url(as: 'projekt', except: '')]
    public string $projectId = '';

    public function save(CreateTicket $createTicket): void
    {
        $schluessel = 'kundenportal-ticket:'.$this->portalUser()->id;

        if (RateLimiter::tooManyAttempts($schluessel, 10)) {
            throw ValidationException::withMessages([
                'subject' => 'Sie haben in kurzer Zeit sehr viele Tickets eröffnet. Bitte versuchen Sie es gleich noch einmal.',
            ]);
        }

        $ticket = $createTicket($this->customer(), [
            'subject' => $this->subject,
            'body' => $this->body,
            'priority' => $this->priority,
            'project_id' => blank($this->projectId) ? null : (int) $this->projectId,
        ], TicketSource::Portal, $this->portalUser());

        RateLimiter::hit($schluessel, 3600);

        $this->redirectRoute('customer-portal.tickets.show', $ticket, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.customer-portal.ticket-form', [
            'priorities' => TicketPriority::options(),
            'projects' => Project::query()
                ->whereBelongsTo($this->customer())
                ->notArchived()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Project $project): array => ['label' => $project->name, 'value' => (string) $project->id])
                ->all(),
        ])->title('Anliegen melden');
    }
}
