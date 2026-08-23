<?php

namespace App\Livewire\Dashboard;

use App\Actions\Reporting\CalculatePortalMetrics;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard mit den Kennzahlen der Phase 1.
 */
#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class DashboardPage extends Component
{
    public function render(CalculatePortalMetrics $calculatePortalMetrics): View
    {
        return view('livewire.dashboard.dashboard-page', [
            'metrics' => $calculatePortalMetrics(),
        ]);
    }
}
