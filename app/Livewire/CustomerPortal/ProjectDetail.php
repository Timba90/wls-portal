<?php

namespace App\Livewire\CustomerPortal;

use App\Actions\CustomerPortal\CalculateProjectStatistics;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ein Projekt aus Kundensicht: Fortschritt, Meilensteine, Betrieb, Tickets.
 *
 * Positionen und Preise erscheinen hier nicht — sie sind interne Kalkulation.
 */
#[Layout('components.layouts.customer')]
class ProjectDetail extends Component
{
    use WithPortalCustomer;

    #[Locked]
    public int $projectId;

    public function mount(Project $project): void
    {
        abort_unless(
            $project->customer_id === $this->customer()->id && $project->status !== ProjectStatus::Archived,
            404,
        );

        $this->projectId = $project->id;
    }

    public function render(CalculateProjectStatistics $statistics): View
    {
        $project = Project::query()
            ->whereBelongsTo($this->customer())
            ->with(['projectType', 'responsibleUser', 'milestones'])
            ->findOrFail($this->projectId);

        return view('livewire.customer-portal.project-detail', [
            'project' => $project,
            'stats' => $statistics->forProject($project),
            // Erledigtes links, Offenes rechts — wie ein Fortschrittsbalken.
            'milestoneStatuses' => [MilestoneStatus::Done, MilestoneStatus::Skipped, MilestoneStatus::InProgress, MilestoneStatus::Open],
            'tickets' => Ticket::query()->whereBelongsTo($project)->latest('last_activity_at')->limit(5)->get(),
        ])->title($project->name);
    }
}
