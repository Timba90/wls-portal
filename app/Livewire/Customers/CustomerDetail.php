<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\ArchiveCustomer;
use App\Actions\Customers\RestoreCustomer;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kundendetailseite — die wichtigste Ansicht der Anwendung.
 *
 * Die Bereiche Ansprechpartner, Leistungen, Notizen, Dokumente und Historie
 * werden in den folgenden Meilensteinen befuellt.
 */
#[Layout('components.layouts.app')]
class CustomerDetail extends Component
{
    public Customer $customer;

    #[Url(as: 'bereich', except: 'uebersicht')]
    public string $tab = 'uebersicht';

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function archive(ArchiveCustomer $archiveCustomer): void
    {
        $archiveCustomer($this->customer);

        $this->customer->refresh();

        $this->dispatch('kunde-archiviert');
    }

    public function restore(RestoreCustomer $restoreCustomer): void
    {
        $restoreCustomer($this->customer);

        $this->customer->refresh();

        $this->dispatch('kunde-reaktiviert');
    }

    public function render(): View
    {
        return view('livewire.customers.customer-detail')
            ->title($this->customer->displayName());
    }
}
