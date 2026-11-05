<?php

namespace App\Livewire\CustomerPortal;

use App\Actions\CustomerPortal\LoadCustomerInvoices;
use App\Actions\CustomerPortal\LoadSepaMandates;
use App\Enums\InvoicePaymentState;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Models\Project;
use App\Models\Ticket;
use App\Support\Flux\FluxException;
use App\Support\FluxAmount;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Startseite des Kundenportals: das Wichtigste aus allen vier Bereichen.
 *
 * Faellt Flux aus, bleibt die Seite benutzbar — Tickets und Projekte kommen
 * aus dem Portal selbst, nur die Abrechnungskacheln zeigen dann einen Hinweis.
 */
#[Layout('components.layouts.customer')]
class DashboardPage extends Component
{
    use WithPortalCustomer;

    public function render(LoadCustomerInvoices $loadInvoices, LoadSepaMandates $loadMandates): View
    {
        $customer = $this->customer();

        $billing = $this->billingSummary($loadInvoices, $loadMandates);

        $projects = Project::query()
            ->whereBelongsTo($customer)
            ->open()
            ->with(['milestones', 'projectType'])
            ->orderBy('deadline')
            ->limit(4)
            ->get();

        $tickets = Ticket::query()
            ->whereBelongsTo($customer)
            ->latest('last_activity_at')
            ->limit(5)
            ->get();

        return view('livewire.customer-portal.dashboard-page', [
            'customer' => $customer,
            'billing' => $billing,
            'projects' => $projects,
            'openProjectCount' => Project::query()->whereBelongsTo($customer)->open()->count(),
            'tickets' => $tickets,
            'openTicketCount' => Ticket::query()->whereBelongsTo($customer)->open()->count(),
        ])->title('Start');
    }

    /**
     * @return array{available: bool, linked: bool, openCount: int, openAmount: Money, hasMandate: bool}
     */
    private function billingSummary(LoadCustomerInvoices $loadInvoices, LoadSepaMandates $loadMandates): array
    {
        $customer = $this->customer();

        $leer = ['available' => true, 'linked' => $customer->isLinkedToFlux(), 'openCount' => 0, 'openAmount' => Money::zero(), 'hasMandate' => false];

        if (! $customer->isLinkedToFlux()) {
            return $leer;
        }

        try {
            $offen = collect($loadInvoices($customer))
                ->filter(fn (array $rechnung): bool => InvoicePaymentState::tryFrom((string) $rechnung['payment_state'])?->isOutstanding() ?? false);

            return [
                ...$leer,
                'openCount' => $offen->count(),
                'openAmount' => $offen->reduce(
                    fn (Money $summe, array $rechnung): Money => $summe->plus(FluxAmount::toMoney($rechnung['balance'] ?? $rechnung['total_gross_price'])),
                    Money::zero(),
                ),
                'hasMandate' => $loadMandates($customer) !== [],
            ];
        } catch (FluxException) {
            return [...$leer, 'available' => false];
        }
    }
}
