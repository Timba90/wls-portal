<?php

namespace App\Livewire\CustomerPortal;

use App\Actions\CustomerPortal\CalculateProjectStatistics;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Projekte des Kunden mit Fortschritt, Terminen und Betriebsstatus.
 */
#[Layout('components.layouts.customer')]
class ProjectList extends Component
{
    use WithPortalCustomer;

    #[Url(as: 'ansicht', except: 'laufend')]
    public string $view = 'laufend';

    public function render(CalculateProjectStatistics $statistics): View
    {
        $customer = $this->customer();

        $projects = Project::query()
            ->whereBelongsTo($customer)
            ->notArchived()
            ->when($this->view === 'laufend', fn ($query) => $query->open())
            ->when($this->view === 'abgeschlossen', fn ($query) => $query->whereNotIn('status', Project::openStatusValues()))
            ->with(['projectType', 'milestones'])
            ->withCount(['tickets as open_tickets_count' => fn ($query) => $query->open()])
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->orderBy('name')
            ->get();

        return view('livewire.customer-portal.project-list', [
            'projects' => $projects,
            'stats' => $statistics->forCustomer($customer),
        ])->title('Projekte');
    }
}
