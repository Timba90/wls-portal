<?php

namespace App\Livewire\CustomerPortal;

use App\Actions\CustomerPortal\LoadCustomerInvoices;
use App\Enums\InvoicePaymentState;
use App\Livewire\Concerns\WithPortalCustomer;
use App\Support\Flux\FluxException;
use App\Support\FluxAmount;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rechnungen des Kunden aus Flux, zum Herunterladen als PDF.
 */
#[Layout('components.layouts.customer')]
class InvoiceList extends Component
{
    use WithPortalCustomer;

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'stand', except: 'alle')]
    public string $state = 'alle';

    public function refreshInvoices(): void
    {
        LoadCustomerInvoices::forget($this->customer());
    }

    public function render(LoadCustomerInvoices $loadInvoices): View
    {
        $customer = $this->customer();
        $fehler = null;
        $rechnungen = collect();

        try {
            $rechnungen = collect($loadInvoices($customer))->map(fn (array $rechnung): array => [
                ...$rechnung,
                'state' => InvoicePaymentState::tryFrom((string) $rechnung['payment_state']),
                'gross' => FluxAmount::toMoney($rechnung['total_gross_price']),
                'net' => FluxAmount::toMoney($rechnung['total_net_price']),
                'balance_money' => FluxAmount::toMoney($rechnung['balance']),
            ]);
        } catch (FluxException) {
            $fehler = 'Ihre Rechnungen können gerade nicht abgerufen werden. Bitte versuchen Sie es später erneut.';
        }

        $offen = $rechnungen->filter(fn (array $rechnung): bool => $rechnung['state']?->isOutstanding() ?? false);

        return view('livewire.customer-portal.invoice-list', [
            'linked' => $customer->isLinkedToFlux(),
            'error' => $fehler,
            'invoices' => $this->filter($rechnungen),
            'totalCount' => $rechnungen->count(),
            'openCount' => $offen->count(),
            'openAmount' => $offen->reduce(fn (Money $summe, array $rechnung): Money => $summe->plus($rechnung['balance_money']), Money::zero()),
            'yearAmount' => $rechnungen
                ->filter(fn (array $rechnung): bool => str_starts_with((string) $rechnung['invoice_date'], (string) now()->year))
                ->reduce(fn (Money $summe, array $rechnung): Money => $summe->plus($rechnung['gross']), Money::zero()),
        ])->title('Rechnungen');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rechnungen
     * @return Collection<int, array<string, mixed>>
     */
    private function filter(Collection $rechnungen): Collection
    {
        return $rechnungen
            ->when($this->state === 'offen', fn (Collection $liste): Collection => $liste->filter(fn (array $rechnung): bool => $rechnung['state']?->isOutstanding() ?? false))
            ->when($this->state === 'bezahlt', fn (Collection $liste): Collection => $liste->filter(fn (array $rechnung): bool => ! ($rechnung['state']?->isOutstanding() ?? false)))
            ->when(filled($this->search), fn (Collection $liste): Collection => $liste->filter(
                fn (array $rechnung): bool => str_contains(mb_strtolower($rechnung['invoice_number']), mb_strtolower(trim($this->search))),
            ))
            ->values();
    }
}
