<?php

namespace App\Livewire\Services;

use App\Enums\CustomerServiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Money;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Leistungsbereich der Kundendetailseite.
 */
class CustomerServices extends Component
{
    public Customer $customer;

    public bool $showArchived = false;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function render(): View
    {
        $services = $this->services();

        return view('livewire.services.customer-services', [
            'services' => $services,
            'monthlyRevenue' => $this->sum($services, fn (CustomerService $service): Money => $service->monthlyRevenue()),
            'yearlyRevenue' => $this->sum($services, fn (CustomerService $service): Money => $service->yearlyRevenue()),
            'monthlyCosts' => $this->sum($services, fn (CustomerService $service): Money => $service->monthlyCosts()),
            'monthlyMargin' => $this->sum($services, fn (CustomerService $service): Money => $service->monthlyMargin()),
        ]);
    }

    /**
     * @return Collection<int, CustomerService>
     */
    private function services(): Collection
    {
        return $this->customer
            ->services()
            ->with(['product', 'productVariant', 'tags'])
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy($this->statusOrder())
            ->orderBy('name')
            ->get();
    }

    /**
     * Sortierschluessel: aktive Leistungen zuerst, archivierte zuletzt.
     */
    private function statusOrder(): Expression
    {
        $cases = collect(CustomerServiceStatus::cases())
            ->map(fn (CustomerServiceStatus $status, int $index): string => sprintf(
                "WHEN '%s' THEN %d",
                $status->value,
                match ($status) {
                    CustomerServiceStatus::Active => 0,
                    CustomerServiceStatus::Planned => 1,
                    CustomerServiceStatus::Paused => 2,
                    CustomerServiceStatus::Ended => 3,
                    CustomerServiceStatus::Archived => 4,
                },
            ))
            ->implode(' ');

        return DB::raw("CASE status {$cases} ELSE 9 END");
    }

    /**
     * Summiert eine Kennzahl ueber alle abrechnungsrelevanten Leistungen.
     *
     * @param  Collection<int, CustomerService>  $services
     * @param  callable(CustomerService): Money  $value
     */
    private function sum(Collection $services, callable $value): Money
    {
        return $services
            ->filter(fn (CustomerService $service): bool => $service->countsTowardsRevenue())
            ->reduce(
                fn (Money $carry, CustomerService $service): Money => $carry->plus($value($service)),
                Money::zero(),
            );
    }
}
