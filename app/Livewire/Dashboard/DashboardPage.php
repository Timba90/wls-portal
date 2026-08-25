<?php

namespace App\Livewire\Dashboard;

use App\Actions\Reporting\CalculateBillingForecast;
use App\Actions\Reporting\CalculatePortalMetrics;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Uebersicht: Kennzahlen des Bestands und der Rhythmus der Abrechnung.
 */
#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class DashboardPage extends Component
{
    public function render(
        CalculatePortalMetrics $calculatePortalMetrics,
        CalculateBillingForecast $calculateBillingForecast,
    ): View {
        return view('livewire.dashboard.dashboard-page', [
            'metrics' => $calculatePortalMetrics(),
            'forecast' => $calculateBillingForecast(),
        ]);
    }
}
