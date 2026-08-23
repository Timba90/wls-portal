<?php

namespace App\Livewire\Services;

use App\Actions\Services\ArchiveCustomerService;
use App\Actions\Services\ChangeCustomerServiceStatus;
use App\Actions\Services\RestoreCustomerService;
use App\Actions\Services\SetDoNotBill;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Models\Customer;
use App\Models\CustomerService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detailseite einer Kundenleistung.
 */
#[Layout('components.layouts.app')]
class CustomerServiceDetail extends Component
{
    public Customer $customer;

    public CustomerService $service;

    public bool $showDoNotBillForm = false;

    public string $doNotBillReason = DoNotBillReason::Included->value;

    public function mount(Customer $customer, CustomerService $service): void
    {
        abort_unless($service->customer_id === $customer->getKey(), 404);

        $this->customer = $customer;
        $this->service = $service;
    }

    public function changeStatus(string $status, ChangeCustomerServiceStatus $changeStatus): void
    {
        $changeStatus($this->service, CustomerServiceStatus::from($status));

        $this->service->refresh();

        $this->dispatch('status-geaendert');
    }

    public function markDoNotBill(SetDoNotBill $setDoNotBill): void
    {
        $this->validate(
            ['doNotBillReason' => ['required', Rule::in(DoNotBillReason::values())]],
            attributes: ['doNotBillReason' => 'Grund'],
        );

        $setDoNotBill->mark($this->service, DoNotBillReason::from($this->doNotBillReason));

        $this->service->refresh();
        $this->showDoNotBillForm = false;

        $this->dispatch('nicht-abrechnen-gesetzt');
    }

    public function releaseDoNotBill(SetDoNotBill $setDoNotBill): void
    {
        $setDoNotBill->release($this->service);

        $this->service->refresh();

        $this->dispatch('nicht-abrechnen-entfernt');
    }

    public function archive(ArchiveCustomerService $archiveCustomerService): void
    {
        $archiveCustomerService($this->service);

        $this->service->refresh();

        $this->dispatch('leistung-archiviert');
    }

    public function restore(RestoreCustomerService $restoreCustomerService): void
    {
        $restoreCustomerService($this->service);

        $this->service->refresh();

        $this->dispatch('leistung-reaktiviert');
    }

    public function render(): View
    {
        $this->service->load([
            'product', 'productVariant', 'category', 'subcategory',
            'responsibleUser', 'tags', 'serviceComponents',
        ]);

        return view('livewire.services.customer-service-detail', [
            'statusOptions' => CustomerServiceStatus::options(CustomerServiceStatus::selectable()),
            'doNotBillReasonOptions' => DoNotBillReason::options(),
            'deviations' => $this->service->catalogDeviations(),
        ])->title($this->service->name);
    }
}
